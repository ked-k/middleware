<?php

namespace Database\Factories;

use App\Models\FieldMapping;
use App\Models\Integration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FieldMapping>
 */
class FieldMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'integration_id' => Integration::factory(),
            'source_field' => fake()->word(),
            'target_field' => fake()->word(),
            'transforms' => [],
            'is_required' => false,
            'skip_if_empty' => false,
            'sort_order' => 0,
        ];
    }
}
