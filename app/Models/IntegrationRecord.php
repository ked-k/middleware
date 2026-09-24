<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One source record's delivery state for an integration (the sync ledger).
 *
 * @property int $id
 * @property int $integration_id
 * @property string $source_key
 * @property string|null $target_key
 * @property string $status
 * @property string|null $payload_hash
 * @property int $attempts
 * @property string|null $last_error
 * @property int|null $last_run_id
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'integration_id', 'source_key', 'target_key', 'status', 'payload_hash',
    'attempts', 'last_error', 'last_run_id', 'synced_at',
])]
class IntegrationRecord extends Model
{
    /**
     * synced: delivered; failed: target rejected it (retried next run);
     * invalid: couldn't be mapped (fix mappings/lookups, it retries next run).
     *
     * @var array<int, string>
     */
    public const STATUSES = ['synced', 'failed', 'invalid'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Integration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * @return BelongsTo<IntegrationRun, $this>
     */
    public function lastRun(): BelongsTo
    {
        return $this->belongsTo(IntegrationRun::class, 'last_run_id');
    }
}
