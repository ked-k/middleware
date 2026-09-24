<?php

namespace App\Models;

use App\Support\Mapping\Lookup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A reusable lookup table used by the "lookup" transform step.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property array<int, array{from: string, to: string|null}> $entries
 * @property string $fallback
 * @property string|null $fallback_value
 * @property bool $case_insensitive
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'entries', 'fallback', 'fallback_value', 'case_insensitive'])]
class ValueMap extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entries' => 'array',
            'case_insensitive' => 'boolean',
        ];
    }

    public function toLookup(): Lookup
    {
        return new Lookup($this->name, $this->entries ?? [], $this->fallback, $this->fallback_value, $this->case_insensitive);
    }

    /**
     * Entries with no target value filled in yet.
     */
    public function unmappedCount(): int
    {
        return collect($this->entries ?? [])->filter(fn ($e) => ($e['to'] ?? '') === '' || $e['to'] === null)->count();
    }

    /**
     * Render entries as editable "from => to" lines.
     */
    public function entriesAsText(): string
    {
        return collect($this->entries ?? [])
            ->map(fn ($e) => $e['from'].' => '.($e['to'] ?? ''))
            ->implode("\n");
    }

    /**
     * Parse "from => to" lines. A line without "=>" is a known source value
     * whose target hasn't been decided yet.
     *
     * @return array<int, array{from: string, to: string|null}>
     */
    public static function parseEntries(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => $line !== '' && ! str_starts_with($line, '#'))
            ->map(function ($line) {
                [$from, $to] = array_pad(explode('=>', $line, 2), 2, null);
                $to = $to === null ? null : trim($to);

                return ['from' => trim($from), 'to' => $to === '' ? null : $to];
            })
            ->filter(fn ($e) => $e['from'] !== '')
            ->unique('from')
            ->values()
            ->all();
    }

    /**
     * Field mappings whose pipeline uses this table.
     */
    public function usageCount(): int
    {
        return FieldMapping::query()->whereNotNull('transforms')->get()
            ->filter(fn (FieldMapping $m) => collect($m->transforms)->contains(
                fn ($s) => ($s['type'] ?? null) === 'lookup' && (string) ($s['param'] ?? '') === (string) $this->id,
            ))->count();
    }
}
