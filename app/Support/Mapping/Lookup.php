<?php

namespace App\Support\Mapping;

/**
 * An in-memory, DB-free view of a ValueMap (lookup table) — translates a
 * source code/label into the target system's code/ID, e.g.
 * "Blood" (NIMS specimen_type) → 1 (SyncLab sample_type_id).
 */
class Lookup
{
    /** What to do when a value has no entry in the table. */
    public const FALLBACKS = ['fail', 'pass', 'null', 'default'];

    /** @var array<string, string|null> normalized "from" => "to" */
    protected array $index = [];

    /**
     * @param  array<int, array{from: string, to: string|null}>  $entries
     */
    public function __construct(
        public string $name,
        array $entries,
        public string $fallback = 'fail',
        public ?string $fallbackValue = null,
        public bool $caseInsensitive = true,
    ) {
        foreach ($entries as $entry) {
            $this->index[$this->normalize((string) ($entry['from'] ?? ''))] = isset($entry['to']) && $entry['to'] !== ''
                ? (string) $entry['to']
                : null;
        }
    }

    public function translate(mixed $value): mixed
    {
        // Empty in, empty out — "required" is what should catch missing data.
        if ($value === null || $value === '') {
            return $value;
        }

        if (is_array($value)) {
            return array_map(fn ($v) => $this->translate($v), $value);
        }

        $key = $this->normalize((string) $value);

        if (array_key_exists($key, $this->index)) {
            if ($this->index[$key] === null) {
                throw new MappingException("Lookup \"{$this->name}\" has an entry for \"{$value}\" but no target value set yet.");
            }

            return $this->index[$key];
        }

        return match ($this->fallback) {
            'pass' => $value,
            'null' => null,
            'default' => $this->fallbackValue,
            default => throw new MappingException("Lookup \"{$this->name}\" has no entry for \"{$value}\"."),
        };
    }

    protected function normalize(string $value): string
    {
        $value = trim($value);

        return $this->caseInsensitive ? mb_strtolower($value) : $value;
    }
}
