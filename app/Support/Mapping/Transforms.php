<?php

namespace App\Support\Mapping;

use Carbon\Carbon;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

/**
 * The transform steps a field mapping can chain together. Each step takes
 * the value produced by the previous one, so "trim → lookup → to_integer"
 * turns " Blood " into 1.
 *
 * Every step can also see the whole source record (for templates) and the
 * whole source response ("$root", for values that live outside the list —
 * e.g. NIMS's top-level "institution").
 */
class Transforms
{
    /**
     * type => [label, param label (null = no param), param placeholder]
     *
     * @var array<string, array{0: string, 1: string|null, 2: string|null}>
     */
    public const TYPES = [
        'trim' => ['Trim whitespace', null, null],
        'uppercase' => ['Uppercase', null, null],
        'lowercase' => ['Lowercase', null, null],
        'title_case' => ['Title Case', null, null],
        'date_format' => ['Format date', 'Output format', 'Y-m-d'],
        'default' => ['Default if empty', 'Default value', 'N/A'],
        'constant' => ['Constant value', 'Value', '1'],
        'when_present' => ['Constant if source has a value', 'Value', 'mL'],
        'lookup' => ['Lookup table', 'Lookup table', null],
        'template' => ['Template', 'Template', '{district}, {state}[, {country}]'],
        'replace' => ['Find & replace', 'find|replace', 'M|Male'],
        'to_integer' => ['To integer', null, null],
        'to_number' => ['To number', null, null],
        'to_string' => ['To text', null, null],
        'to_boolean' => ['To true/false', null, null],
        'join_list' => ['Join list into text', 'Separator', ', '],
        'split' => ['Split text into list', 'Separator', ','],
        'null_if_empty' => ['Empty → null', null, null],
    ];

    /**
     * @param  Closure(int|string): (Lookup|null)|null  $lookupResolver
     */
    public function __construct(protected ?Closure $lookupResolver = null)
    {
    }

    /**
     * @param  array<int, array{type: string, param?: string|null}>  $steps
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $root
     */
    public function run(array $steps, mixed $value, array $record = [], array $root = []): mixed
    {
        foreach ($steps as $step) {
            $value = $this->apply($step['type'] ?? '', $step['param'] ?? null, $value, $record, $root);
        }

        return $value;
    }

    public function apply(string $type, ?string $param, mixed $value, array $record = [], array $root = []): mixed
    {
        return match ($type) {
            'trim' => is_string($value) ? trim($value) : $value,
            'uppercase' => $value === null ? null : Str::upper((string) $value),
            'lowercase' => $value === null ? null : Str::lower((string) $value),
            'title_case' => $value === null ? null : Str::title((string) $value),
            'date_format' => $this->formatDate($value, $param ?: 'Y-m-d'),
            'default' => self::isBlank($value) ? $param : $value,
            'constant' => $param,
            'when_present' => self::isBlank($value) ? null : $param,
            'lookup' => $this->lookup($param)->translate($value),
            'template' => self::render((string) $param, $record, $root, $value),
            'replace' => $this->replace($value, (string) $param),
            'to_integer' => $this->toNumber($value, true),
            'to_number' => $this->toNumber($value, false),
            'to_string' => $value === null ? null : (is_array($value) ? self::joinList($value, ', ') : (string) $value),
            'to_boolean' => $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'join_list' => $value === null ? null : self::joinList($value, $param ?? ', '),
            'split' => $value === null ? null : array_values(array_filter(array_map('trim', explode($param ?: ',', (string) $value)), fn ($v) => $v !== '')),
            'null_if_empty' => self::isBlank($value) ? null : $value,
            'none', '' => $value,
            default => throw new MappingException("Unknown transform \"{$type}\"."),
        };
    }

    /**
     * Read a value from a record by dot path. "$root.x" reads from the whole
     * source response instead of the current record.
     */
    public static function read(?string $path, array $record, array $root = []): mixed
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, '$root.')) {
            return Arr::get($root, substr($path, 6));
        }

        return Arr::get($record, $path);
    }

    /**
     * Render "{field}" placeholders against the record. "{value}" is the
     * incoming pipeline value. A "[ ... ]" section is dropped entirely when
     * any placeholder inside it is empty — so "[Symptoms: {symptoms}]"
     * disappears for records without symptoms instead of leaving a dangling
     * label.
     */
    public static function render(string $template, array $record, array $root = [], mixed $value = null): ?string
    {
        $resolve = function (string $path) use ($record, $root, $value): string {
            $raw = $path === 'value' ? $value : self::read($path, $record, $root);

            return self::isBlank($raw) ? '' : (string) self::joinList($raw, ', ');
        };

        // Optional sections first.
        $template = preg_replace_callback('/\[([^\[\]]*)\]/', function ($m) use ($resolve) {
            $empty = false;
            $out = preg_replace_callback('/\{([^{}]+)\}/', function ($p) use ($resolve, &$empty) {
                $v = $resolve(trim($p[1]));
                $empty = $empty || $v === '';

                return $v;
            }, $m[1]);

            return $empty ? '' : $out;
        }, $template);

        $out = preg_replace_callback('/\{([^{}]+)\}/', fn ($p) => $resolve(trim($p[1])), $template);
        $out = trim(preg_replace('/\s{2,}/', ' ', $out));

        return $out === '' ? null : $out;
    }

    /**
     * Arrays, and JSON-encoded arrays stored as strings (NIMS sends symptoms
     * both as "Fever, Cough" and as "[\"Fever\",\"Headache\"]"), become
     * "a, b". Anything else passes through.
     */
    public static function joinList(mixed $value, string $separator): mixed
    {
        if (is_string($value) && str_starts_with(ltrim($value), '[')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && array_is_list($decoded)) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            return implode($separator, array_filter(array_map(
                fn ($v) => is_scalar($v) ? trim((string) $v) : json_encode($v),
                $value,
            ), fn ($v) => $v !== ''));
        }

        return $value;
    }

    public static function isBlank(mixed $value): bool
    {
        return $value === null || $value === [] || (is_string($value) && trim($value) === '');
    }

    protected function formatDate(mixed $value, string $format): mixed
    {
        if (self::isBlank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format($format);
        } catch (Throwable) {
            throw new MappingException("\"{$value}\" is not a date that can be read.");
        }
    }

    protected function toNumber(mixed $value, bool $integer): int|float|null
    {
        if (self::isBlank($value)) {
            return null;
        }

        if (is_bool($value)) {
            return (int) $value;
        }

        if (! is_numeric($value)) {
            throw new MappingException("\"{$value}\" is not a number.");
        }

        return $integer ? (int) round((float) $value) : $value + 0;
    }

    protected function replace(mixed $value, string $param): mixed
    {
        if ($value === null || ! str_contains($param, '|')) {
            return $value;
        }

        [$find, $replace] = explode('|', $param, 2);

        // Whole-value match replaces the value outright (so "M|Male" doesn't
        // turn "Male" into "Maleale"); otherwise substring replace.
        if (is_string($value) && strcasecmp(trim($value), $find) === 0) {
            return $replace;
        }

        return is_string($value) ? str_replace($find, $replace, $value) : $value;
    }

    protected function lookup(?string $id): Lookup
    {
        $lookup = ($id !== null && $id !== '' && $this->lookupResolver) ? ($this->lookupResolver)($id) : null;

        if (! $lookup) {
            throw new MappingException("Lookup table #{$id} does not exist.");
        }

        return $lookup;
    }
}
