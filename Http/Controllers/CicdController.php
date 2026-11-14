<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MultiTenantSaas\Context\TenantContext;
use MultiTenantSaas\Http\Controllers\BaseController;
use MultiTenantSaas\Modules\Cicd\Contracts\CicdProjectLinkContract;
use MultiTenantSaas\Modules\Cicd\Contracts\CicdReadContract;
use MultiTenantSaas\Modules\Cicd\Contracts\CicdSyncContract;
use MultiTenantSaas\Modules\Cicd\Models\Deployment;
use MultiTenantSaas\Modules\Cicd\Models\Pipeline;
use MultiTenantSaas\Modules\Cicd\Models\ProjectRepository;
use MultiTenantSaas\Modules\Cicd\Models\Release;
use MultiTenantSaas\Modules\Cicd\Models\Repository;
use MultiTenantSaas\Modules\Cicd\Models\Workspace;
use MultiTenantSaas\Modules\Cicd\Services\CicdWorkspaceService;

/**
 * CI/CD 跟踪只读接口 + 手动同步。
 *
 * 所有查询走 TenantScope；{workspace}/{repository} 由当前租户内查找，
 * 跨租户 ID 直接 404，不返回他租户数据。
 */
class CicdController extends BaseController
{
    public function __construct(
        private readonly CicdSyncContract $sync,
        private readonly CicdReadContract $read,
        private readonly CicdProjectLinkContract $links,
        private readonly CicdWorkspaceService $workspaces,
    ) {}

    public function overview(): JsonResponse
    {
        $workspaces = Workspace::query()->count();
        $repositories = Repository::query()->count();
        $pipelines = Pipeline::query()->count();
        $releases = Release::query()->count();
        $deployments = Deployment::query()->count();
        $failedPipelines = Pipeline::query()->where('status', Pipeline::STATUS_FAILED)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'workspaces' => $workspaces,
                'repositories' => $repositories,
                'pipelines' => $pipelines,
                'releases' => $releases,
                'deployments' => $deployments,
                'failed_pipelines' => $failedPipelines,
            ],
        ]);
    }

    public function workspaces(Request $request): JsonResponse
    {
        $perPage = $this->perPage($request);

        $page = Workspace::query()
            ->withCount('repositories')
            ->orderByDesc('last_synced_at')
            ->orderByDesc((new Workspace)->getKeyName())
            ->paginate($perPage);

        return $this->page($page, fn (Workspace $workspace): array => [
            'workspace_id' => (string) $workspace->workspace_id,
            'provider_key' => $workspace->provider_key,
            'name' => $workspace->name,
            'group_path' => $workspace->group_path,
            'external_group_id' => $workspace->external_group_id,
            'sync_status' => $workspace->sync_status,
            'last_synced_at' => $workspace->last_synced_at?->toIso8601String(),
            'last_sync_error' => $workspace->last_sync_error,
            'repositories_count' => (int) ($workspace->repositories_count ?? 0),
        ]);
    }

    public function storeWorkspace(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_key' => 'required|in:gitlab,github,yunxiao',
            'name' => 'nullable|string|max:191',
            'external_group_id' => 'required|string|max:100',
            'group_path' => 'nullable|string|max:191',
            'connection_id' => 'nullable|integer|min:1',
            'metadata' => 'nullable|array',
        ]);

        $workspace = $this->workspaces->create((int) TenantContext::getId(), $data);

        return response()->json(['success' => true, 'data' => $this->workspaceRow($workspace)], 201);
    }

    public function updateWorkspace(Request $request, string $workspaceId): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:191',
            'group_path' => 'sometimes|nullable|string|max:191',
            'connection_id' => 'sometimes|nullable|integer|min:1',
            'metadata' => 'sometimes|nullable|array',
        ]);

        $workspace = $this->workspaces->update((int) TenantContext::getId(), $workspaceId, $data);

        return response()->json(['success' => true, 'data' => $this->workspaceRow($workspace)]);
    }

    public function repositories(Request $request, string $workspaceId): JsonResponse
    {
        $workspace = $this->workspace($workspaceId);
        $perPage = $this->perPage($request);

        $page = Repository::query()
            ->where('workspace_id', $workspace->workspace_id)
            ->when($request->query('keyword'), fn ($q, $keyword) => $q->where('path', 'like', '%'.$keyword.'%'))
            ->orderByDesc('last_activity_at')
            ->orderByDesc((new Repository)->getKeyName())
            ->paginate($perPage);

        return $this->page($page, fn (Repository $repository): array => [
            'repo_id' => (string) $repository->repo_id,
            'external_id' => $repository->external_id,
            'path' => $repository->path,
            'name' => $repository->name,
            'default_branch' => $repository->default_branch,
            'web_url' => $repository->web_url,
            'archived' => $repository->archived,
            'last_commit_sha' => $repository->last_commit_sha,
            'last_activity_at' => $repository->last_activity_at?->toIso8601String(),
        ]);
    }

    public function pipelines(Request $request, string $repositoryId): JsonResponse
    {
        $repository = $this->repository($repositoryId);
        $perPage = $this->perPage($request);

        $page = Pipeline::query()
            ->where('repo_id', $repository->repo_id)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('finished_at')
            ->orderByDesc((new Pipeline)->getKeyName())
            ->paginate($perPage);

        return $this->page($page, fn (Pipeline $pipeline): array => [
            'pipeline_id' => (string) $pipeline->pipeline_id,
            'external_id' => $pipeline->external_id,
            'ref' => $pipeline->ref,
            'commit_sha' => $pipeline->commit_sha,
            'status' => $pipeline->status,
            'source' => $pipeline->source,
            'duration' => $pipeline->duration,
            'started_at' => $pipeline->started_at?->toIso8601String(),
            'finished_at' => $pipeline->finished_at?->toIso8601String(),
            'web_url' => $pipeline->web_url,
        ]);
    }

    public function releases(Request $request, string $repositoryId): JsonResponse
    {
        $repository = $this->repository($repositoryId);
        $perPage = $this->perPage($request);

        $page = Release::query()
            ->where('repo_id', $repository->repo_id)
            ->orderByDesc('released_at')
            ->orderByDesc((new Release)->getKeyName())
            ->paginate($perPage);

        return $this->page($page, fn (Release $release): array => [
            'release_id' => (string) $release->release_id,
            'external_id' => $release->external_id,
            'version' => $release->version,
            'commit_sha' => $release->commit_sha,
            'name' => $release->name,
            'artifact_url' => $release->artifact_url,
            'released_at' => $release->released_at?->toIso8601String(),
            'web_url' => $release->web_url,
        ]);
    }

    public function deployments(Request $request, string $repositoryId): JsonResponse
    {
        $repository = $this->repository($repositoryId);
        $perPage = $this->perPage($request);

        $page = Deployment::query()
            ->where('repo_id', $repository->repo_id)
            ->when($request->query('environment'), fn ($q, $environment) => $q->where('environment', $environment))
            ->orderByDesc('deployed_at')
            ->orderByDesc((new Deployment)->getKeyName())
            ->paginate($perPage);

        return $this->page($page, fn (Deployment $deployment): array => [
            'deployment_id' => (string) $deployment->deployment_id,
            'external_id' => $deployment->external_id,
            'environment' => $deployment->environment,
            'status' => $deployment->status,
            'deployed_at' => $deployment->deployed_at?->toIso8601String(),
            'operator' => $deployment->operator,
            'web_url' => $deployment->web_url,
        ]);
    }

    public function projectRepositories(string $projectId): JsonResponse
    {
        $links = $this->links->links((int) TenantContext::getId(), $projectId);

        return response()->json([
            'success' => true,
            'data' => $links->map(fn (ProjectRepository $link): array => $this->linkRow($link))->all(),
        ]);
    }

    public function linkProjectRepository(Request $request, string $projectId): JsonResponse
    {
        $data = $request->validate([
            'repo_id' => 'required',
            'enabled' => 'sometimes|boolean',
        ]);

        $link = $this->links->link(
            (int) TenantContext::getId(),
            $projectId,
            $data['repo_id'],
            (bool) ($data['enabled'] ?? true),
        );

        return response()->json(['success' => true, 'data' => $this->linkRow($link->loadMissing('repository'))], 201);
    }

    public function unlinkProjectRepository(string $projectId, string $repositoryId): JsonResponse
    {
        $this->links->unlink((int) TenantContext::getId(), $projectId, $repositoryId);

        return response()->json(['success' => true, 'message' => '已解绑']);
    }

    public function projectStatus(Request $request, string $projectId): JsonResponse
    {
        $commit = trim((string) $request->query('commit', ''));
        $tenantId = (int) TenantContext::getId();
        $pipeline = $this->read->latestPipeline($tenantId, $projectId, $commit !== '' ? $commit : null);
        $release = $this->read->latestRelease($tenantId, $projectId, $commit !== '' ? $commit : null);

        return response()->json([
            'success' => true,
            'data' => [
                'project_id' => (string) $projectId,
                'commit' => $commit !== '' ? $commit : null,
                'ready' => $pipeline?->status === Pipeline::STATUS_PASSED,
                'pipeline' => $pipeline === null ? null : [
                    'pipeline_id' => (string) $pipeline->pipeline_id,
                    'external_id' => $pipeline->external_id,
                    'status' => $pipeline->status,
                    'ref' => $pipeline->ref,
                    'commit_sha' => $pipeline->commit_sha,
                    'finished_at' => $pipeline->finished_at?->toIso8601String(),
                    'web_url' => $pipeline->web_url,
                ],
                'release' => $release === null ? null : [
                    'release_id' => (string) $release->release_id,
                    'external_id' => $release->external_id,
                    'version' => $release->version,
                    'commit_sha' => $release->commit_sha,
                    'released_at' => $release->released_at?->toIso8601String(),
                    'web_url' => $release->web_url,
                ],
            ],
        ]);
    }

    public function sync(string $workspaceId): JsonResponse
    {
        $workspace = $this->workspace($workspaceId);
        $result = $this->sync->syncWorkspace($workspace);

        return response()->json(['success' => true, 'data' => $result]);
    }

    private function workspaceRow(Workspace $workspace): array
    {
        return [
            'workspace_id' => (string) $workspace->workspace_id,
            'provider_key' => $workspace->provider_key,
            'name' => $workspace->name,
            'external_group_id' => $workspace->external_group_id,
            'group_path' => $workspace->group_path,
            'connection_id' => $workspace->connection_id === null ? null : (string) $workspace->connection_id,
            'sync_status' => $workspace->sync_status,
            'last_synced_at' => $workspace->last_synced_at?->toIso8601String(),
            'last_sync_error' => $workspace->last_sync_error,
        ];
    }

    private function linkRow(ProjectRepository $link): array
    {
        $repository = $link->repository;

        return [
            'link_id' => (string) $link->link_id,
            'project_id' => (string) $link->project_id,
            'repo_id' => (string) $link->repo_id,
            'enabled' => $link->enabled,
            'repository' => $repository === null ? null : [
                'repo_id' => (string) $repository->repo_id,
                'external_id' => $repository->external_id,
                'path' => $repository->path,
                'name' => $repository->name,
                'web_url' => $repository->web_url,
            ],
        ];
    }

    private function workspace(string $id): Workspace
    {
        return Workspace::query()->findOrFail($id);
    }

    private function repository(string $id): Repository
    {
        return Repository::query()->findOrFail($id);
    }

    private function perPage(Request $request): int
    {
        $value = (int) $request->query('per_page', (int) config('cicd.per_page', 20));

        return max(1, min(100, $value));
    }

    private function page(LengthAwarePaginator $page, callable $mapper): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'data' => collect($page->items())->map($mapper)->all(),
                'total' => $page->total(),
            ],
        ]);
    }
}
