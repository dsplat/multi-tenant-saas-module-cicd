<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Services;

use MultiTenantSaas\Exceptions\DomainException;
use MultiTenantSaas\Exceptions\NotFoundException;
use MultiTenantSaas\Modules\Cicd\Models\Workspace;
use MultiTenantSaas\Modules\Integration\Models\IntegrationConnection;
use MultiTenantSaas\Scopes\TenantScope;

/**
 * 工作区配置：provider + 分组/组织 + connection。
 *
 * provider/external_group_id 是同步对账键，创建后不可改；name/group_path/connection 可更新。
 */
final class CicdWorkspaceService
{
    public const PROVIDERS = ['gitlab', 'github', 'yunxiao'];

    public function create(int $tenantId, array $data): Workspace
    {
        return TenantScope::allowUnscoped(function () use ($tenantId, $data): Workspace {
            $provider = (string) ($data['provider_key'] ?? '');
            $externalGroupId = trim((string) ($data['external_group_id'] ?? ''));

            $this->assertProvider($provider);

            if ($externalGroupId === '') {
                throw new DomainException('external_group_id 不能为空');
            }

            $connectionId = $this->connectionId($tenantId, $provider, $data['connection_id'] ?? null);

            $exists = Workspace::query()
                ->where('tenant_id', $tenantId)
                ->where('provider_key', $provider)
                ->where('external_group_id', $externalGroupId)
                ->exists();

            if ($exists) {
                throw new DomainException('该 provider 下已存在同名工作区');
            }

            return Workspace::create([
                'tenant_id' => $tenantId,
                'provider_key' => $provider,
                'name' => (string) ($data['name'] ?? $externalGroupId),
                'external_group_id' => $externalGroupId,
                'group_path' => $data['group_path'] ?? $externalGroupId,
                'connection_id' => $connectionId,
                'sync_status' => Workspace::SYNC_IDLE,
                'metadata' => $data['metadata'] ?? null,
            ]);
        });
    }

    public function update(int $tenantId, int|string $workspaceId, array $data): Workspace
    {
        return TenantScope::allowUnscoped(function () use ($tenantId, $workspaceId, $data): Workspace {
            $workspace = Workspace::query()
                ->where('tenant_id', $tenantId)
                ->find($workspaceId);

            if (! $workspace) {
                throw new NotFoundException('工作区不存在');
            }

            $attributes = [];

            if (array_key_exists('name', $data)) {
                $attributes['name'] = (string) $data['name'];
            }
            if (array_key_exists('group_path', $data)) {
                $attributes['group_path'] = $data['group_path'];
            }
            if (array_key_exists('connection_id', $data)) {
                $attributes['connection_id'] = $this->connectionId(
                    $tenantId,
                    (string) $workspace->provider_key,
                    $data['connection_id'],
                );
            }
            if (array_key_exists('metadata', $data)) {
                $attributes['metadata'] = $data['metadata'];
            }

            $workspace->fill($attributes)->save();

            return $workspace->refresh();
        });
    }

    private function assertProvider(string $provider): void
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            throw new DomainException('provider_key 必须是 gitlab/github/yunxiao');
        }
    }

    private function connectionId(int $tenantId, string $provider, mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $connection = IntegrationConnection::query()
            ->where('tenant_id', $tenantId)
            ->find((int) $value);

        if (! $connection) {
            throw new NotFoundException('连接不存在');
        }

        if ((string) $connection->provider !== $provider) {
            throw new DomainException('连接 provider 与工作区 provider 不一致');
        }

        return (int) $connection->connection_id;
    }
}
