<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MultiTenantSaas\Concerns\BelongsToTenant;
use MultiTenantSaas\Concerns\HasGlobalId;

/**
 * 平台业务项目 ↔ CI/CD 仓库绑定。
 *
 * 平台侧发布确认按 project_id 查询时，先经本表定位仓库。
 */
class ProjectRepository extends Model
{
    use BelongsToTenant, HasGlobalId;

    protected $table = 'cicd_project_repositories';

    protected $primaryKey = 'link_id';

    protected $fillable = [
        'tenant_id', 'project_id', 'repo_id', 'enabled', 'metadata',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'metadata' => 'array',
        'project_id' => 'integer',
        'repo_id' => 'integer',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'repo_id', 'repo_id');
    }
}
