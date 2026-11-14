<?php

declare(strict_types=1);

return [
    'enabled' => true,

    'route_prefix' => '',

    'per_page' => 20,

    // 单次命令最多处理的 workspace 数
    'sync_limit' => 100,

    // 已注册/可用的 provider 标识（适配模块决定实际注册）
    'providers' => ['gitlab', 'github', 'yunxiao'],
];
