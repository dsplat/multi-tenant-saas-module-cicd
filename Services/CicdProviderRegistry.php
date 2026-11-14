<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Services;

use MultiTenantSaas\Modules\Cicd\Contracts\CicdProviderContract;

/**
 * provider 注册表：核心不依赖任何厂商，适配模块在 ServiceProvider 中注册。
 */
final class CicdProviderRegistry
{
    /** @var array<string, CicdProviderContract> */
    private array $providers = [];

    public function register(CicdProviderContract $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function get(string $key): ?CicdProviderContract
    {
        return $this->providers[$key] ?? null;
    }

    /** @return array<string, CicdProviderContract> */
    public function all(): array
    {
        return $this->providers;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->providers);
    }
}
