<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Cicd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MultiTenantSaas\Concerns\BelongsToTenant;
use MultiTenantSaas\Concerns\HasGlobalId;

/**
 * 流水线/构建投影：provider 原生状态归一化为 queued|running|passed|failed|canceled|skipped。
 */
class Pipeline extends Model
{
    use BelongsToTenant, HasGlobalId;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_PASSED = 'passed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_RUNNING,
        self::STATUS_PASSED,
        self::STATUS_FAILED,
        self::STATUS_CANCELED,
        self::STATUS_SKIPPED,
    ];

    protected $table = 'cicd_pipelines';

    protected $primaryKey = 'pipeline_id';

    protected $fillable = [
        'tenant_id', 'repo_id', 'external_id', 'ref', 'commit_sha', 'status',
        'source', 'duration', 'started_at', 'finished_at', 'web_url', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'duration' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'repo_id' => 'integer',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'repo_id', 'repo_id');
    }
}
