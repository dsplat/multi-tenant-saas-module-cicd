<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Models;

use Illuminate\Database\Eloquent\Model;
use MultiTenantSaas\Concerns\BelongsToTenant;
use MultiTenantSaas\Concerns\HasGlobalId;

/**
 * 增量同步游标：按 workspace + 资源记录 provider 侧游标。
 */
class SyncCursor extends Model
{
    use BelongsToTenant, HasGlobalId;

    protected $table = 'cicd_sync_cursors';

    protected $primaryKey = 'cursor_id';

    protected $fillable = [
        'tenant_id', 'workspace_id', 'resource', 'cursor', 'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'workspace_id' => 'integer',
    ];
}
