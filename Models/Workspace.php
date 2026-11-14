<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MultiTenantSaas\Concerns\BelongsToTenant;
use MultiTenantSaas\Concerns\HasGlobalId;

/**
 * CI/CD 工作区：一个 provider 下的分组/组织（GitLab group、GitHub org、云效项目组）。
 */
class Workspace extends Model
{
    use BelongsToTenant, HasGlobalId;

    public const SYNC_IDLE = 'idle';

    public const SYNC_SYNCING = 'syncing';

    public const SYNC_OK = 'ok';

    public const SYNC_FAILED = 'failed';

    public const SYNC_UNSUPPORTED = 'unsupported';

    public const SYNC_STATUSES = [
        self::SYNC_IDLE,
        self::SYNC_SYNCING,
        self::SYNC_OK,
        self::SYNC_FAILED,
        self::SYNC_UNSUPPORTED,
    ];

    protected $table = 'cicd_workspaces';

    protected $primaryKey = 'workspace_id';

    protected $fillable = [
        'tenant_id', 'provider_key', 'name', 'external_group_id', 'group_path',
        'connection_id', 'sync_status', 'last_synced_at', 'last_sync_error', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_synced_at' => 'datetime',
        'connection_id' => 'integer',
    ];

    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class, 'workspace_id', 'workspace_id');
    }
}
