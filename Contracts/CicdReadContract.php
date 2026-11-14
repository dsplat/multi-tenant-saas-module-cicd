<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Contracts;

use MultiTenantSaas\Modules\Cicd\Models\Pipeline;
use MultiTenantSaas\Modules\Cicd\Models\Release;

/**
 * 平台侧只读端口：按业务项目 + commit 查 CI/CD 事实。
 *
 * 由 Cicd 模块实现；下游交付模块通过容器注入消费，不直接查 cicd_* 表。
 */
interface CicdReadContract
{
    public function latestPipeline(int $tenantId, int|string $projectId, ?string $commitSha = null): ?Pipeline;

    public function latestRelease(int $tenantId, int|string $projectId, ?string $commitSha = null): ?Release;

    public function isCommitPassed(int $tenantId, int|string $projectId, string $commitSha): bool;
}
