<?php

namespace App\Support\Mapping;

use Illuminate\Support\Arr;

/**
 * Maps one source record into one target payload using an ordered list of
 * rules. Never throws: problems are returned as per-record errors so a
 * single bad sample doesn't sink a batch of 100.
 */
class RecordMapper
{
    /**
     * @param  array<int, array{source_field: ?string, target_field: string, transforms: array, is_required: bool, skip_if_empty: bool}>  $rules
     */
    public function __construct(protected array $rules, protected Transforms $transforms)
    {
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $root
     * @return array{payload: array<string, mixed>, errors: array<int, string>}
     */
    public function map(array $record, array $root = []): array
    {
        $payload = [];
        $errors = [];

        foreach ($this->rules as $rule) {
            $target = $rule['target_field'];

            try {
                $value = Transforms::read($rule['source_field'] ?? null, $record, $root);
                $value = $this->transforms->run($rule['transforms'] ?? [], $value, $record, $root);
            } catch (MappingException $e) {
                $errors[] = "{$target}: {$e->getMessage()}";

                continue;
            }

            if (Transforms::isBlank($value)) {
                if ($rule['is_required'] ?? false) {
                    $from = $rule['source_field'] ? " (from {$rule['source_field']})" : '';
                    $errors[] = "{$target}: required but empty{$from}.";

                    continue;
                }

                if ($rule['skip_if_empty'] ?? false) {
                    continue;
                }
            }

            Arr::set($payload, $target, $value);
        }

        return ['payload' => $payload, 'errors' => $errors];
    }
}
