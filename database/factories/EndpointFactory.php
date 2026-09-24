<?php

namespace Database\Factories;

use App\Models\Endpoint;
use App\Models\System;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Endpoint>
 */
class EndpointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'system_id' => System::factory(),
            'name' => ucwords(fake()->unique()->words(2, true)),
            'method' => fake()->randomElement(Endpoint::METHODS),
            'path' => '/'.fake()->word(),
            'description' => fake()->optional()->sentence(),
            'request_schema' => null,
            'response_schema' => null,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the endpoint is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
