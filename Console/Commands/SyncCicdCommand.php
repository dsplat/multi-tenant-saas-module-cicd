<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Console\Commands;

use Illuminate\Console\Command;
use MultiTenantSaas\Modules\Cicd\Contracts\CicdSyncContract;
use MultiTenantSaas\Modules\Cicd\Models\Workspace;
use MultiTenantSaas\Scopes\TenantScope;

/**
 * CI/CD 投影同步。
 *
 * 系统级任务：显式 tenant_id，不使用默认租户；同步逻辑在 CicdSyncService 内
 * 用 TenantScope::allowUnscoped 声明系统级读取。
 */
class SyncCicdCommand extends Command
{
    protected $signature = 'cicd:sync
        {--workspace= : 只同步指定 workspace_id}
        {--tenant= : 只同步指定租户}
        {--provider= : 只同步指定 provider（gitlab|github|yunxiao）}
        {--limit=100 : 单次最多处理的 workspace 数}';

    protected $description = '同步 CI/CD 工作区、仓库、流水线、发布与部署投影';

    public function handle(CicdSyncContract $sync): int
    {
        $query = Workspace::query()
            ->when($this->option('workspace'), fn ($q, $id) => $q->where('workspace_id', $id))
            ->when($this->option('tenant'), fn ($q, $id) => $q->where('tenant_id', $id))
            ->when($this->option('provider'), fn ($q, $provider) => $q->where('provider_key', $provider))
            ->orderBy('workspace_id')
            ->limit(max(1, (int) $this->option('limit')));

        $workspaces = TenantScope::allowUnscoped(fn () => $query->get());

        if ($workspaces->isEmpty()) {
            $this->info('没有可同步的 workspace。');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($workspaces as $workspace) {
            $result = $sync->syncWorkspace($workspace);
            $line = sprintf(
                '[%s] %s/%s repos=%d pipelines=%d releases=%d deployments=%d',
                $result['status'],
                $workspace->provider_key,
                $workspace->group_path ?: $workspace->external_group_id,
                $result['repositories'],
                $result['pipelines'],
                $result['releases'],
                $result['deployments'],
            );

            if ($result['status'] === Workspace::SYNC_FAILED || $result['status'] === Workspace::SYNC_UNSUPPORTED) {
                $failed++;
                $this->warn($line);
                foreach ($result['errors'] as $error) {
                    $this->line('  - ' . $error);
                }
            } else {
                $this->info($line);
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
