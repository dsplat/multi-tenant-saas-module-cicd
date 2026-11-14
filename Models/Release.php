<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MultiTenantSaas\Concerns\BelongsToTenant;
use MultiTenantSaas\Concerns\HasGlobalId;

/**
 * 发布投影：tag/release/版本记录。
 */
class Release extends Model
{
    use BelongsToTenant, HasGlobalId;

    protected $table = 'cicd_releases';

    protected $primaryKey = 'release_id';

    protected $fillable = [
        'tenant_id', 'repo_id', 'external_id', 'version', 'commit_sha', 'name',
        'notes', 'artifact_url', 'released_at', 'web_url', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'released_at' => 'datetime',
        'repo_id' => 'integer',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'repo_id', 'repo_id');
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class, 'release_id', 'release_id');
    }
}
