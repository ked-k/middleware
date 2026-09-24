<?php

namespace Database\Factories;

use App\Models\Connection;
use App\Models\Endpoint;
use App\Models\Integration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Integration>
 */
class IntegrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->unique()->words(3, true)),
            'description' => fake()->optional()->sentence(),
            'source_connection_id' => Connection::factory(),
            'source_endpoint_id' => Endpoint::factory(),
            'target_connection_id' => Connection::factory(),
            'target_endpoint_id' => Endpoint::factory(),
            'is_active' => true,
        ];
    }
}
