<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MultiTenantSaas\Concerns\BelongsToTenant;
use MultiTenantSaas\Concerns\HasGlobalId;

/**
 * 部署投影：环境、版本、结果、执行人。
 */
class Deployment extends Model
{
    use BelongsToTenant, HasGlobalId;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELED = 'canceled';

    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_RUNNING,
        self::STATUS_SUCCESS,
        self::STATUS_FAILED,
        self::STATUS_CANCELED,
    ];

    protected $table = 'cicd_deployments';

    protected $primaryKey = 'deployment_id';

    protected $fillable = [
        'tenant_id', 'repo_id', 'release_id', 'external_id', 'environment',
        'status', 'deployed_at', 'operator', 'web_url', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'deployed_at' => 'datetime',
        'repo_id' => 'integer',
        'release_id' => 'integer',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'repo_id', 'repo_id');
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'release_id', 'release_id');
    }
}
