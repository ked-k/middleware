<?php

namespace App\Support\Mapping;

/**
 * Decides whether a source record should be sent at all, e.g.
 * "status in Result Added, Received" or "identifier is not empty".
 * All conditions must pass (AND).
 */
class RecordFilter
{
    /** operator => label */
    public const OPERATORS = [
        'equals' => 'equals',
        'not_equals' => 'does not equal',
        'in' => 'is one of (comma-separated)',
        'not_in' => 'is not one of (comma-separated)',
        'contains' => 'contains',
        'not_contains' => 'does not contain',
        'empty' => 'is empty',
        'not_empty' => 'is not empty',
        'gt' => 'is greater than',
        'lt' => 'is less than',
        'after' => 'date is after',
        'before' => 'date is before',
    ];

    /**
     * @param  array<int, array{field: string, operator: string, value?: string|null}>  $conditions
     */
    public static function passes(array $record, array $conditions, array $root = []): bool
    {
        foreach ($conditions as $condition) {
            if (! self::check($record, $condition, $root)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Human-readable reason the first failing condition failed, or null.
     */
    public static function reason(array $record, array $conditions, array $root = []): ?string
    {
        foreach ($conditions as $c) {
            if (! self::check($record, $c, $root)) {
                $label = self::OPERATORS[$c['operator']] ?? $c['operator'];

                return trim("{$c['field']} {$label} ".($c['value'] ?? ''));
            }
        }

        return null;
    }

    protected static function check(array $record, array $c, array $root): bool
    {
        $actual = Transforms::read($c['field'] ?? '', $record, $root);
        $expected = (string) ($c['value'] ?? '');
        $a = is_scalar($actual) ? mb_strtolower(trim((string) $actual)) : '';
        $e = mb_strtolower(trim($expected));
        $list = fn () => array_map(fn ($v) => mb_strtolower(trim($v)), explode(',', $expected));

        return match ($c['operator'] ?? 'equals') {
            'equals' => $a === $e,
            'not_equals' => $a !== $e,
            'in' => in_array($a, $list(), true),
            'not_in' => ! in_array($a, $list(), true),
            'contains' => $e !== '' && str_contains($a, $e),
            'not_contains' => $e === '' || ! str_contains($a, $e),
            'empty' => Transforms::isBlank($actual),
            'not_empty' => ! Transforms::isBlank($actual),
            'gt' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'after' => ($t = strtotime((string) $actual)) !== false && ($x = strtotime($expected)) !== false && $t > $x,
            'before' => ($t = strtotime((string) $actual)) !== false && ($x = strtotime($expected)) !== false && $t < $x,
            default => true,
        };
    }
}
