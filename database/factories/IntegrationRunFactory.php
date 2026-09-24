<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\IntegrationRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<IntegrationRun>
 */
class IntegrationRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = Date::now()->subMinutes(fake()->numberBetween(1, 1000));

        return [
            'integration_id' => Integration::factory(),
            'status' => fake()->randomElement(IntegrationRun::STATUSES),
            'trigger' => fake()->randomElement(IntegrationRun::TRIGGERS),
            'attempt' => 1,
            'request_payload' => null,
            'response_payload' => null,
            'error' => null,
            'started_at' => $startedAt,
            'finished_at' => $startedAt->clone()->addMilliseconds(fake()->numberBetween(50, 4000)),
            'duration_ms' => fake()->numberBetween(50, 4000),
        ];
    }
}
