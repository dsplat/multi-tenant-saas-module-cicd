<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Services;

use Illuminate\Support\Collection;
use MultiTenantSaas\Exceptions\NotFoundException;
use MultiTenantSaas\Modules\Cicd\Contracts\CicdProjectLinkContract;
use MultiTenantSaas\Modules\Cicd\Models\ProjectRepository;
use MultiTenantSaas\Modules\Cicd\Models\Repository;
use MultiTenantSaas\Scopes\TenantScope;

/**
 * 项目 ↔ 仓库绑定；显式 tenant_id，跨模块/CLI 调用不改请求上下文。
 */
final class CicdProjectLinkService implements CicdProjectLinkContract
{
    public function links(int $tenantId, int|string $projectId): Collection
    {
        return TenantScope::allowUnscoped(fn (): Collection => ProjectRepository::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->with('repository')
            ->orderBy('link_id')
            ->get());
    }

    public function link(int $tenantId, int|string $projectId, int|string $repoId, bool $enabled = true): ProjectRepository
    {
        return TenantScope::allowUnscoped(function () use ($tenantId, $projectId, $repoId, $enabled): ProjectRepository {
            $repository = Repository::query()
                ->where('tenant_id', $tenantId)
                ->find($repoId);

            if (! $repository) {
                throw new NotFoundException('仓库不存在');
            }

            return ProjectRepository::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'project_id' => $projectId,
                    'repo_id' => $repository->repo_id,
                ],
                ['enabled' => $enabled],
            );
        });
    }

    public function unlink(int $tenantId, int|string $projectId, int|string $repoId): void
    {
        TenantScope::allowUnscoped(function () use ($tenantId, $projectId, $repoId): void {
            $deleted = ProjectRepository::query()
                ->where('tenant_id', $tenantId)
                ->where('project_id', $projectId)
                ->where('repo_id', $repoId)
                ->delete();

            if ($deleted === 0) {
                throw new NotFoundException('项目未绑定该仓库');
            }
        });
    }
}
