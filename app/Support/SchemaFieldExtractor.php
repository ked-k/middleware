<?php

namespace App\Support;

/**
 * Flattens an endpoint's request/response schema into dot-notation field
 * paths, so the field mapper can offer suggestions instead of forcing the
 * user to type every path from memory.
 *
 * Understands two shapes:
 *  - JSON Schema style: {"type": "object", "properties": {"foo": {...}}}
 *  - A plain example payload: {"foo": "bar", "nested": {"baz": 1}}
 */
class SchemaFieldExtractor
{
    /**
     * @param  array<string, mixed>|null  $schema
     * @return array<int, string>
     */
    public static function extractPaths(?array $schema): array
    {
        if (blank($schema)) {
            return [];
        }

        $paths = [];

        self::walk($schema, '', $paths);

        sort($paths);

        return array_values(array_unique($paths));
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, string>  $paths
     */
    protected static function walk(array $node, string $prefix, array &$paths): void
    {
        // JSON Schema object node.
        if (isset($node['properties']) && is_array($node['properties'])) {
            foreach ($node['properties'] as $key => $child) {
                $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
                $paths[] = $path;

                if (is_array($child)) {
                    self::walk($child, $path, $paths);
                }
            }

            return;
        }

        // JSON Schema array node — describe items under a wildcard segment.
        if (isset($node['items']) && is_array($node['items'])) {
            self::walk($node['items'], $prefix === '' ? '*' : "{$prefix}.*", $paths);

            return;
        }

        // Plain example payload containing a list — describe the first item
        // under a wildcard segment, e.g. data.*.sample_id.
        if (array_is_list($node)) {
            if (isset($node[0]) && is_array($node[0])) {
                self::walk($node[0], $prefix === '' ? '*' : "{$prefix}.*", $paths);
            }

            return;
        }

        // Plain example payload — recurse into associative arrays.
        foreach ($node as $key => $value) {
            if (is_int($key)) {
                continue;
            }

            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            $paths[] = $path;

            if (is_array($value)) {
                self::walk($value, $path, $paths);
            }
        }
    }

    /**
     * Paths relative to each item of the list at $collectionPath — what a
     * bulk integration's field mappings actually see. Other top-level paths
     * are offered with the "$root." prefix.
     *
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    public static function relativeTo(array $paths, ?string $collectionPath, bool $includeRoot = true): array
    {
        if (blank($collectionPath)) {
            return $paths;
        }

        $prefix = $collectionPath.'.*.';
        $relative = [];

        foreach ($paths as $path) {
            if (str_starts_with($path, $prefix)) {
                $relative[] = substr($path, strlen($prefix));
            } elseif ($includeRoot && ! str_starts_with($path, $collectionPath)) {
                $relative[] = '$root.'.$path;
            }
        }

        return array_values(array_unique($relative));
    }
}
