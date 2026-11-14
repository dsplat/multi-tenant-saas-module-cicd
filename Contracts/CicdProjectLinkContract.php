<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Contracts;

use Illuminate\Support\Collection;
use MultiTenantSaas\Modules\Cicd\Models\ProjectRepository;

/**
 * 平台业务项目 ↔ CI/CD 仓库绑定的写入口。
 */
interface CicdProjectLinkContract
{
    /** @return Collection<int, ProjectRepository> */
    public function links(int $tenantId, int|string $projectId): Collection;

    public function link(int $tenantId, int|string $projectId, int|string $repoId, bool $enabled = true): ProjectRepository;

    public function unlink(int $tenantId, int|string $projectId, int|string $repoId): void;
}
