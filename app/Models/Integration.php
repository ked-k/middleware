<?php

namespace App\Models;

use Database\Factories\IntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $source_connection_id
 * @property int $source_endpoint_id
 * @property int $target_connection_id
 * @property int $target_endpoint_id
 * @property bool $is_active
 * @property string|null $schedule_cron
 * @property string|null $last_run_status
 * @property Carbon|null $last_run_at
 * @property int $consecutive_failures
 * @property bool $is_bulk
 * @property string|null $source_collection_path
 * @property string $bulk_mode
 * @property array<int, array{field: string, operator: string, value: string|null}>|null $record_filters
 * @property string|null $source_key_field  Unique ID of a record in the source (e.g. NIMS identifier) — enables dedup
 * @property string|null $target_key_field  Field in the mapped payload carrying that ID (e.g. SyncLab sid)
 * @property bool $skip_synced
 * @property bool $resend_on_change
 * @property int|null $batch_size
 * @property string|null $target_wrapper_path  Wrap batch bodies, e.g. "samples" → {"samples": [...]}
 * @property string|null $response_collection_path  List of per-record results in a batch response, e.g. "data.samples"
 * @property string|null $response_id_path  Target-assigned ID per record, e.g. "lab_no" (batch) or "data.lab_no" (single)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'description',
    'source_connection_id', 'source_endpoint_id',
    'target_connection_id', 'target_endpoint_id',
    'is_active', 'schedule_cron',
    'is_bulk', 'source_collection_path', 'bulk_mode',
    'record_filters', 'source_key_field', 'target_key_field', 'skip_synced', 'resend_on_change',
    'batch_size', 'target_wrapper_path', 'response_collection_path', 'response_id_path',
])]
class Integration extends Model
{
    /** @use HasFactory<IntegrationFactory> */
    use HasFactory;

    /**
     * The strategies for pushing multiple mapped records to the target
     * endpoint when `is_bulk` is enabled.
     *
     * @var array<int, string>
     */
    public const BULK_MODES = ['per_item', 'single_request'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_bulk' => 'boolean',
            'record_filters' => 'array',
            'skip_synced' => 'boolean',
            'resend_on_change' => 'boolean',
            'batch_size' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * The authenticated connection data is read from.
     *
     * @return BelongsTo<Connection, $this>
     */
    public function sourceConnection(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'source_connection_id');
    }

    /**
     * The endpoint data is read from.
     *
     * @return BelongsTo<Endpoint, $this>
     */
    public function sourceEndpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class, 'source_endpoint_id');
    }

    /**
     * The authenticated connection data is written to.
     *
     * @return BelongsTo<Connection, $this>
     */
    public function targetConnection(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'target_connection_id');
    }

    /**
     * The endpoint data is written to.
     *
     * @return BelongsTo<Endpoint, $this>
     */
    public function targetEndpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class, 'target_endpoint_id');
    }

    /**
     * The field-by-field mapping and transformation rules for this integration.
     *
     * @return HasMany<FieldMapping, $this>
     */
    public function fieldMappings(): HasMany
    {
        return $this->hasMany(FieldMapping::class)->orderBy('sort_order');
    }

    /**
     * The execution history for this integration, most recent first.
     *
     * @return HasMany<IntegrationRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(IntegrationRun::class)->latest('id');
    }

    /**
     * The sync ledger — every source record this integration has handled.
     *
     * @return HasMany<IntegrationRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(IntegrationRecord::class);
    }
}
