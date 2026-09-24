<?php

namespace App\Models;

use App\Support\Mapping\Transforms;
use Carbon\Carbon;
use Database\Factories\FieldMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $integration_id
 * @property string|null $source_field  Dot path in the source record; "$root.x" reads the whole response; blank for constants/templates
 * @property string $target_field
 * @property array<int, array{type: string, param: string|null}>|null $transforms
 * @property bool $is_required
 * @property bool $skip_if_empty
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['integration_id', 'source_field', 'target_field', 'transforms', 'is_required', 'skip_if_empty', 'sort_order'])]
class FieldMapping extends Model
{
    /** @use HasFactory<FieldMappingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transforms' => 'array',
            'is_required' => 'boolean',
            'skip_if_empty' => 'boolean',
        ];
    }

    /**
     * The integration this mapping rule belongs to.
     *
     * @return BelongsTo<Integration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * Plain-array form consumed by RecordMapper.
     *
     * @return array{source_field: ?string, target_field: string, transforms: array, is_required: bool, skip_if_empty: bool}
     */
    public function toRule(): array
    {
        return [
            'source_field' => $this->source_field,
            'target_field' => $this->target_field,
            'transforms' => $this->transforms ?? [],
            'is_required' => $this->is_required,
            'skip_if_empty' => $this->skip_if_empty,
        ];
    }

    /**
     * Lookup-table IDs referenced by this mapping's pipeline.
     *
     * @return array<int, string>
     */
    public function lookupIds(): array
    {
        return collect($this->transforms ?? [])
            ->where('type', 'lookup')
            ->pluck('param')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * Short human description of each step, for the mappings table.
     *
     * @param  array<int|string, string>  $valueMapNames  id => name
     * @return array<int, string>
     */
    public function describeSteps(array $valueMapNames = []): array
    {
        return collect($this->transforms ?? [])->map(function ($step) use ($valueMapNames) {
            $label = Transforms::TYPES[$step['type']][0] ?? $step['type'];
            $param = $step['param'] ?? null;

            if ($step['type'] === 'lookup') {
                $param = $valueMapNames[$param] ?? "#{$param}";
            }

            return filled($param) ? "{$label}: {$param}" : $label;
        })->all();
    }
}
