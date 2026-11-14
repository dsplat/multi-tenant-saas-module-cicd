<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Services;

use Illuminate\Support\Collection;
use MultiTenantSaas\Modules\Cicd\Contracts\CicdReadContract;
use MultiTenantSaas\Modules\Cicd\Models\Pipeline;
use MultiTenantSaas\Modules\Cicd\Models\ProjectRepository;
use MultiTenantSaas\Modules\Cicd\Models\Release;
use MultiTenantSaas\Scopes\TenantScope;

/**
 * 按项目 → 仓库绑定查询流水线/发布；显式 tenant_id，供跨模块/CLI 安全调用。
 */
final class CicdReadService implements CicdReadContract
{
    public function latestPipeline(int $tenantId, int|string $projectId, ?string $commitSha = null): ?Pipeline
    {
        return TenantScope::allowUnscoped(function () use ($tenantId, $projectId, $commitSha): ?Pipeline {
            $repoIds = $this->linkedRepoIds($tenantId, $projectId);

            if ($repoIds->isEmpty()) {
                return null;
            }

            return Pipeline::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('repo_id', $repoIds)
                ->when($commitSha !== null && $commitSha !== '', fn ($query) => $query->where('commit_sha', $commitSha))
                ->orderByDesc('finished_at')
                ->orderByDesc('pipeline_id')
                ->first();
        });
    }

    public function latestRelease(int $tenantId, int|string $projectId, ?string $commitSha = null): ?Release
    {
        return TenantScope::allowUnscoped(function () use ($tenantId, $projectId, $commitSha): ?Release {
            $repoIds = $this->linkedRepoIds($tenantId, $projectId);

            if ($repoIds->isEmpty()) {
                return null;
            }

            return Release::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('repo_id', $repoIds)
                ->when($commitSha !== null && $commitSha !== '', fn ($query) => $query->where('commit_sha', $commitSha))
                ->orderByDesc('released_at')
                ->orderByDesc('release_id')
                ->first();
        });
    }

    public function isCommitPassed(int $tenantId, int|string $projectId, string $commitSha): bool
    {
        return $this->latestPipeline($tenantId, $projectId, $commitSha)?->status === Pipeline::STATUS_PASSED;
    }

    /** @return Collection<int, int> */
    private function linkedRepoIds(int $tenantId, int|string $projectId): Collection
    {
        return ProjectRepository::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->where('enabled', true)
            ->pluck('repo_id');
    }
}
