<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Contracts;

use MultiTenantSaas\Modules\Cicd\Models\Workspace;

/**
 * CI/CD 同步端口：把 provider 数据投影到本地表。
 */
interface CicdSyncContract
{
    /**
     * @return array{status:string,repositories:int,pipelines:int,releases:int,deployments:int,errors:list<string>}
     */
    public function syncWorkspace(Workspace $workspace): array;
}
