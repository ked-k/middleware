<?php

namespace Database\Factories;

use App\Models\Connection;
use App\Models\System;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Connection>
 */
class ConnectionFactory extends Factory
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
            'auth_profile_id' => null,
            'name' => ucwords(fake()->unique()->words(2, true)),
            'status' => 'untested',
            'last_tested_at' => null,
            'last_error' => null,
        ];
    }
}
