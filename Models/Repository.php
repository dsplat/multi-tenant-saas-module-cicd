<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MultiTenantSaas\Concerns\BelongsToTenant;
use MultiTenantSaas\Concerns\HasGlobalId;

/**
 * CI/CD 仓库投影：只存 provider 侧标识与最新状态。
 */
class Repository extends Model
{
    use BelongsToTenant, HasGlobalId;

    protected $table = 'cicd_repositories';

    protected $primaryKey = 'repo_id';

    protected $fillable = [
        'tenant_id', 'workspace_id', 'external_id', 'path', 'name', 'default_branch',
        'web_url', 'archived', 'last_commit_sha', 'last_activity_at', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'archived' => 'boolean',
        'last_activity_at' => 'datetime',
        'workspace_id' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id', 'workspace_id');
    }

    public function pipelines(): HasMany
    {
        return $this->hasMany(Pipeline::class, 'repo_id', 'repo_id');
    }

    public function releases(): HasMany
    {
        return $this->hasMany(Release::class, 'repo_id', 'repo_id');
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class, 'repo_id', 'repo_id');
    }
}
