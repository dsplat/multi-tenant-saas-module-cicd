<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd;

use MultiTenantSaas\Authorization\CapabilityDefinition;
use MultiTenantSaas\Context\ExecutionIdentity;
use MultiTenantSaas\Contracts\AuthorizationCapabilityRegistryContract;
use MultiTenantSaas\Contracts\CapabilityRequirementResolverContract;
use MultiTenantSaas\Contracts\SceneRegistryContract;
use MultiTenantSaas\Contracts\ToolRegistryContract;
use MultiTenantSaas\Modules\Cicd\Console\Commands\SyncCicdCommand;
use MultiTenantSaas\Modules\Cicd\Contracts\CicdProjectLinkContract;
use MultiTenantSaas\Modules\Cicd\Contracts\CicdReadContract;
use MultiTenantSaas\Modules\Cicd\Contracts\CicdSyncContract;
use MultiTenantSaas\Modules\Cicd\Services\CicdProjectLinkService;
use MultiTenantSaas\Modules\Cicd\Services\CicdProviderRegistry;
use MultiTenantSaas\Modules\Cicd\Services\CicdReadService;
use MultiTenantSaas\Modules\Cicd\Services\CicdSyncService;
use MultiTenantSaas\Modules\Cicd\Services\CicdWorkspaceService;
use MultiTenantSaas\Modules\Cicd\Services\Tools\CicdStatusHandler;
use MultiTenantSaas\Modules\Contracts\ModuleServiceProvider;

/**
 * CI/CD 跟踪（工作区/仓库/流水线/发布/部署）
 *
 * 核心只做多租户投影；GitLab / GitHub / 云效适配模块通过 CicdProviderRegistry 注册。
 * 路由 / 迁移 / 视图 / 翻译由基类 ModuleServiceProvider 自动加载。
 */
class CicdServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'cicd';

    /** @var array<string, array{0:string,1:string}> 能力点 => [权限后缀, 说明] */
    private const CAPABILITIES = [
        'view' => ['view', '查看 CI/CD 工作区、仓库、流水线、发布与部署'],
        'manage' => ['manage', '管理工作区与仓库绑定'],
        'sync' => ['sync', '触发 CI/CD 数据同步'],
    ];

    protected function registerModuleBindings(): void
    {
        $this->app->singleton(CicdProviderRegistry::class);
        $this->app->singleton(CicdSyncContract::class, CicdSyncService::class);
        $this->app->singleton(CicdReadContract::class, CicdReadService::class);
        $this->app->singleton(CicdProjectLinkContract::class, CicdProjectLinkService::class);
        $this->app->singleton(CicdWorkspaceService::class);
    }

    protected function bootModule(): void
    {
        $this->registerAuthorizationCapabilities();
        $this->registerTools();
        $this->registerScenes();
    }

    protected function registerModuleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SyncCicdCommand::class]);
        }
    }

    private function registerAuthorizationCapabilities(): void
    {
        $registry = $this->app->make(AuthorizationCapabilityRegistryContract::class);
        $requirements = $this->app->make(CapabilityRequirementResolverContract::class);

        foreach (self::CAPABILITIES as $action => [$permission]) {
            $key = 'common.cicd.' . $action;
            $registry->register(new CapabilityDefinition(
                key: $key,
                subjectTypes: [ExecutionIdentity::TYPE_OPERATOR],
                targetScope: CapabilityDefinition::TARGET_TENANT,
                admissionPolicy: [ExecutionIdentity::TYPE_OPERATOR => ['member']],
            ));
        }

        // 字面量登记：AdminConsolePermissionMapTest 静态解析 capability → permission。
        $requirements->register('common.cicd.view', ExecutionIdentity::TYPE_OPERATOR, ['permission' => 'cicd.view']);
        $requirements->register('common.cicd.manage', ExecutionIdentity::TYPE_OPERATOR, ['permission' => 'cicd.manage']);
        $requirements->register('common.cicd.sync', ExecutionIdentity::TYPE_OPERATOR, ['permission' => 'cicd.sync']);
    }

    private function registerTools(): void
    {
        $registry = $this->app->make(ToolRegistryContract::class);

        $registry->register(
            slug: 'cicd_status',
            name: 'CI/CD 状态',
            description: '汇总当前租户的工作区、仓库、流水线、发布、部署与失败构建；可按 workspace 过滤。',
            handlerClass: CicdStatusHandler::class,
            schema: [
                'type' => 'object',
                'properties' => [
                    'workspace_id' => ['type' => 'string', 'description' => '可选：只统计指定工作区'],
                    'limit' => ['type' => 'integer', 'description' => '最近流水线/发布条数，1-20，默认 5'],
                ],
                'required' => [],
            ],
            category: 'cicd',
            risk: 'L1',
            audience: 'operator',
            requiredCapability: 'common.cicd.view',
        );
    }

    private function registerScenes(): void
    {
        if (! $this->app->bound(SceneRegistryContract::class)) {
            return;
        }

        $registry = $this->app->make(SceneRegistryContract::class);

        $registry->registerScene('cicd', 'CI/CD 跟踪', 'Monitor', 'CI/CD 工作区、流水线与发布跟踪', 100);
        $registry->registerCard('cicd-home', 'cicd', 'CI/CD 跟踪', '分组、仓库、流水线与发布', null, null, '/cicd');
    }
}
