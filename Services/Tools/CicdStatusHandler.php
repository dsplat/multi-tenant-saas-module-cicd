<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Services\Tools;

use MultiTenantSaas\Modules\Ai\Services\Agent\Contracts\ToolHandlerContract;
use MultiTenantSaas\Modules\Cicd\Models\Deployment;
use MultiTenantSaas\Modules\Cicd\Models\Pipeline;
use MultiTenantSaas\Modules\Cicd\Models\Release;
use MultiTenantSaas\Modules\Cicd\Models\Repository;
use MultiTenantSaas\Modules\Cicd\Models\Workspace;
use MultiTenantSaas\Scopes\TenantScope;

/**
 * cicd_status：按租户汇总 CI/CD 投影（只读）。
 *
 * 显式 tenant_id + 系统级读取；不读请求上下文，不跨租户。
 */
class CicdStatusHandler implements ToolHandlerContract
{
    public function __invoke(array $arguments, int $tenantId): mixed
    {
        return TenantScope::allowUnscoped(function () use ($arguments, $tenantId): array {
            $workspaceIds = Workspace::query()
                ->where('tenant_id', $tenantId)
                ->when($arguments['workspace_id'] ?? null, fn ($q, $id) => $q->where('workspace_id', $id))
                ->pluck('workspace_id');

            $repoIds = Repository::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('workspace_id', $workspaceIds)
                ->pluck('repo_id');

            $limit = max(1, min(20, (int) ($arguments['limit'] ?? 5)));

            $latestPipelines = Pipeline::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('repo_id', $repoIds)
                ->orderByDesc('finished_at')
                ->orderByDesc('pipeline_id')
                ->limit($limit)
                ->get(['pipeline_id', 'repo_id', 'status', 'ref', 'commit_sha', 'finished_at', 'web_url'])
                ->map(fn (Pipeline $pipeline): array => [
                    'pipeline_id' => (string) $pipeline->pipeline_id,
                    'repo_id' => (string) $pipeline->repo_id,
                    'status' => $pipeline->status,
                    'ref' => $pipeline->ref,
                    'commit_sha' => $pipeline->commit_sha,
                    'finished_at' => $pipeline->finished_at?->toIso8601String(),
                    'web_url' => $pipeline->web_url,
                ])
                ->all();

            $latestReleases = Release::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('repo_id', $repoIds)
                ->orderByDesc('released_at')
                ->orderByDesc('release_id')
                ->limit($limit)
                ->get(['release_id', 'repo_id', 'external_id', 'version', 'commit_sha', 'released_at', 'web_url'])
                ->map(fn (Release $release): array => [
                    'release_id' => (string) $release->release_id,
                    'repo_id' => (string) $release->repo_id,
                    'tag' => $release->external_id,
                    'version' => $release->version,
                    'commit_sha' => $release->commit_sha,
                    'released_at' => $release->released_at?->toIso8601String(),
                    'web_url' => $release->web_url,
                ])
                ->all();

            return [
                'workspaces' => $workspaceIds->count(),
                'repositories' => $repoIds->count(),
                'pipelines' => $repoIds->isEmpty() ? 0 : Pipeline::query()->where('tenant_id', $tenantId)->whereIn('repo_id', $repoIds)->count(),
                'releases' => $repoIds->isEmpty() ? 0 : Release::query()->where('tenant_id', $tenantId)->whereIn('repo_id', $repoIds)->count(),
                'deployments' => $repoIds->isEmpty() ? 0 : Deployment::query()->where('tenant_id', $tenantId)->whereIn('repo_id', $repoIds)->count(),
                'failed_pipelines' => $repoIds->isEmpty() ? 0 : Pipeline::query()->where('tenant_id', $tenantId)->whereIn('repo_id', $repoIds)->where('status', Pipeline::STATUS_FAILED)->count(),
                'latest_pipelines' => $latestPipelines,
                'latest_releases' => $latestReleases,
            ];
        });
    }
}
