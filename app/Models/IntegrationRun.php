<?php

namespace App\Models;

use Database\Factories\IntegrationRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $integration_id
 * @property string $status
 * @property string $trigger
 * @property int $attempt
 * @property array<string, mixed>|null $request_payload
 * @property array<string, mixed>|null $response_payload
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $duration_ms
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'integration_id', 'status', 'trigger', 'attempt',
    'request_payload', 'response_payload', 'error',
    'started_at', 'finished_at', 'duration_ms',
])]
class IntegrationRun extends Model
{
    /** @use HasFactory<IntegrationRunFactory> */
    use HasFactory;

    /**
     * The lifecycle states a run passes through. "partial" means everything
     * that was sent succeeded, but some records couldn't be mapped (invalid)
     * — it doesn't count toward the failure streak.
     *
     * @var array<int, string>
     */
    public const STATUSES = ['pending', 'success', 'partial', 'failed'];

    /**
     * How a run was started.
     *
     * @var array<int, string>
     */
    public const TRIGGERS = ['manual', 'scheduled', 'retry'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * The integration this execution attempt belongs to.
     *
     * @return BelongsTo<Integration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
