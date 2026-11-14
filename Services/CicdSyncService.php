<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Services;

use Illuminate\Support\Facades\Log;
use MultiTenantSaas\Modules\Cicd\Contracts\CicdProviderContract;
use MultiTenantSaas\Modules\Cicd\Contracts\CicdSyncContract;
use MultiTenantSaas\Modules\Cicd\Models\Deployment;
use MultiTenantSaas\Modules\Cicd\Models\Pipeline;
use MultiTenantSaas\Modules\Cicd\Models\Release;
use MultiTenantSaas\Modules\Cicd\Models\Repository;
use MultiTenantSaas\Modules\Cicd\Models\SyncCursor;
use MultiTenantSaas\Modules\Cicd\Models\Workspace;
use MultiTenantSaas\Scopes\TenantScope;

/**
 * 把 provider 数据幂等投影到 cicd_* 表。
 *
 * 全程显式 tenant_id；CLI/队列下用 TenantScope::allowUnscoped 声明系统级同步，
 * 不依赖默认租户，也不改写请求上下文。
 */
final class CicdSyncService implements CicdSyncContract
{
    public function __construct(private readonly CicdProviderRegistry $providers) {}

    public function syncWorkspace(Workspace $workspace): array
    {
        $provider = $this->providers->get($workspace->provider_key);

        if ($provider === null) {
            $workspace->forceFill([
                'sync_status' => Workspace::SYNC_UNSUPPORTED,
                'last_sync_error' => 'provider_not_registered:' . $workspace->provider_key,
            ])->save();

            return $this->result(Workspace::SYNC_UNSUPPORTED);
        }

        return TenantScope::allowUnscoped(function () use ($workspace, $provider): array {
            $workspace->forceFill(['sync_status' => Workspace::SYNC_SYNCING, 'last_sync_error' => null])->save();

            $result = $this->result(Workspace::SYNC_OK);

            try {
                $repositories = $provider->fetchRepositories($workspace);
            } catch (\Throwable $e) {
                return $this->failed($workspace, $e);
            }

            foreach ($repositories as $data) {
                try {
                    $repository = $this->upsertRepository($workspace, $data);
                    $result['repositories']++;

                    $result['pipelines'] += $this->syncPipelines($provider, $repository, $result['errors']);
                    $result['releases'] += $this->syncReleases($provider, $repository, $result['errors']);
                    $result['deployments'] += $this->syncDeployments($provider, $repository, $result['errors']);
                } catch (\Throwable $e) {
                    $result['errors'][] = 'repo:' . ($data['path'] ?? '?') . ':' . $e->getMessage();
                    Log::warning('[Cicd] repository sync failed', [
                        'tenant_id' => $workspace->tenant_id,
                        'workspace_id' => $workspace->workspace_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $workspace->forceFill([
                'sync_status' => $result['errors'] === [] ? Workspace::SYNC_OK : Workspace::SYNC_FAILED,
                'last_synced_at' => now(),
                'last_sync_error' => $result['errors'] === [] ? null : mb_substr(implode('; ', $result['errors']), 0, 1000),
            ])->save();

            $this->touchCursor($workspace, 'repositories');

            return $result;
        });
    }

    private function result(string $status): array
    {
        return [
            'status' => $status,
            'repositories' => 0,
            'pipelines' => 0,
            'releases' => 0,
            'deployments' => 0,
            'errors' => [],
        ];
    }

    private function failed(Workspace $workspace, \Throwable $e): array
    {
        $workspace->forceFill([
            'sync_status' => Workspace::SYNC_FAILED,
            'last_sync_error' => mb_substr($e->getMessage(), 0, 1000),
        ])->save();

        Log::warning('[Cicd] workspace sync failed', [
            'tenant_id' => $workspace->tenant_id,
            'workspace_id' => $workspace->workspace_id,
            'error' => $e->getMessage(),
        ]);

        $result = $this->result(Workspace::SYNC_FAILED);
        $result['errors'][] = $e->getMessage();

        return $result;
    }

    private function upsertRepository(Workspace $workspace, array $data): Repository
    {
        return Repository::query()->updateOrCreate(
            [
                'tenant_id' => $workspace->tenant_id,
                'workspace_id' => $workspace->workspace_id,
                'external_id' => (string) $data['external_id'],
            ],
            [
                'path' => (string) ($data['path'] ?? $data['external_id']),
                'name' => (string) ($data['name'] ?? $data['path'] ?? $data['external_id']),
                'default_branch' => $data['default_branch'] ?? null,
                'web_url' => $data['web_url'] ?? null,
                'archived' => (bool) ($data['archived'] ?? false),
                'last_commit_sha' => $data['last_commit_sha'] ?? null,
                'last_activity_at' => $data['last_activity_at'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ],
        );
    }

    private function syncPipelines(CicdProviderContract $provider, Repository $repository, array &$errors): int
    {
        try {
            $rows = $provider->fetchPipelines($repository);
        } catch (\Throwable $e) {
            $errors[] = 'pipelines:' . $repository->path . ':' . $e->getMessage();

            return 0;
        }

        $count = 0;

        foreach ($rows as $data) {
            Pipeline::query()->updateOrCreate(
                [
                    'tenant_id' => $repository->tenant_id,
                    'repo_id' => $repository->repo_id,
                    'external_id' => (string) $data['external_id'],
                ],
                [
                    'ref' => $data['ref'] ?? null,
                    'commit_sha' => $data['commit_sha'] ?? null,
                    'status' => $this->normalizePipelineStatus((string) ($data['status'] ?? 'queued')),
                    'source' => $data['source'] ?? null,
                    'duration' => isset($data['duration']) ? (int) $data['duration'] : null,
                    'started_at' => $data['started_at'] ?? null,
                    'finished_at' => $data['finished_at'] ?? null,
                    'web_url' => $data['web_url'] ?? null,
                    'metadata' => $data['metadata'] ?? null,
                ],
            );
            $count++;
        }

        $this->touchCursorForRepository($repository, 'pipelines');

        return $count;
    }

    private function syncReleases(CicdProviderContract $provider, Repository $repository, array &$errors): int
    {
        try {
            $rows = $provider->fetchReleases($repository);
        } catch (\Throwable $e) {
            $errors[] = 'releases:' . $repository->path . ':' . $e->getMessage();

            return 0;
        }

        $count = 0;

        foreach ($rows as $data) {
            Release::query()->updateOrCreate(
                [
                    'tenant_id' => $repository->tenant_id,
                    'repo_id' => $repository->repo_id,
                    'external_id' => (string) $data['external_id'],
                ],
                [
                    'version' => $data['version'] ?? $data['external_id'],
                    'commit_sha' => $data['commit_sha'] ?? null,
                    'name' => $data['name'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'artifact_url' => $data['artifact_url'] ?? null,
                    'released_at' => $data['released_at'] ?? null,
                    'web_url' => $data['web_url'] ?? null,
                    'metadata' => $data['metadata'] ?? null,
                ],
            );
            $count++;
        }

        $this->touchCursorForRepository($repository, 'releases');

        return $count;
    }

    private function syncDeployments(CicdProviderContract $provider, Repository $repository, array &$errors): int
    {
        try {
            $rows = $provider->fetchDeployments($repository);
        } catch (\Throwable $e) {
            $errors[] = 'deployments:' . $repository->path . ':' . $e->getMessage();

            return 0;
        }

        $count = 0;

        foreach ($rows as $data) {
            $releaseId = null;
            if (! empty($data['release_external_id'])) {
                $releaseId = Release::query()
                    ->where('tenant_id', $repository->tenant_id)
                    ->where('repo_id', $repository->repo_id)
                    ->where('external_id', (string) $data['release_external_id'])
                    ->value('release_id');
            }

            Deployment::query()->updateOrCreate(
                [
                    'tenant_id' => $repository->tenant_id,
                    'repo_id' => $repository->repo_id,
                    'external_id' => (string) $data['external_id'],
                ],
                [
                    'release_id' => $releaseId,
                    'environment' => $data['environment'] ?? null,
                    'status' => $this->normalizeDeploymentStatus((string) ($data['status'] ?? 'queued')),
                    'deployed_at' => $data['deployed_at'] ?? null,
                    'operator' => $data['operator'] ?? null,
                    'web_url' => $data['web_url'] ?? null,
                    'metadata' => $data['metadata'] ?? null,
                ],
            );
            $count++;
        }

        $this->touchCursorForRepository($repository, 'deployments');

        return $count;
    }

    private function touchCursor(Workspace $workspace, string $resource): void
    {
        SyncCursor::query()->updateOrCreate(
            [
                'tenant_id' => $workspace->tenant_id,
                'workspace_id' => $workspace->workspace_id,
                'resource' => $resource,
            ],
            ['cursor' => null, 'last_synced_at' => now()],
        );
    }

    private function touchCursorForRepository(Repository $repository, string $resource): void
    {
        SyncCursor::query()->updateOrCreate(
            [
                'tenant_id' => $repository->tenant_id,
                'workspace_id' => $repository->workspace_id,
                'resource' => $resource . ':' . $repository->repo_id,
            ],
            ['cursor' => null, 'last_synced_at' => now()],
        );
    }

    public function normalizePipelineStatus(string $status): string
    {
        return match (strtolower($status)) {
            'created', 'waiting_for_resource', 'preparing', 'pending', 'scheduled', 'manual' => Pipeline::STATUS_QUEUED,
            'running' => Pipeline::STATUS_RUNNING,
            'success', 'passed' => Pipeline::STATUS_PASSED,
            'failed' => Pipeline::STATUS_FAILED,
            'canceled', 'cancelled', 'cancelling' => Pipeline::STATUS_CANCELED,
            'skipped' => Pipeline::STATUS_SKIPPED,
            default => Pipeline::STATUS_QUEUED,
        };
    }

    public function normalizeDeploymentStatus(string $status): string
    {
        return match (strtolower($status)) {
            'running' => Deployment::STATUS_RUNNING,
            'success', 'succeeded' => Deployment::STATUS_SUCCESS,
            'failed' => Deployment::STATUS_FAILED,
            'canceled', 'cancelled' => Deployment::STATUS_CANCELED,
            default => Deployment::STATUS_QUEUED,
        };
    }
}
