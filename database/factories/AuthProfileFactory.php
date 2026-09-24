<?php

namespace Database\Factories;

use App\Models\AuthProfile;
use App\Models\System;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthProfile>
 */
class AuthProfileFactory extends Factory
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
            'type' => 'bearer',
            'credentials' => ['token' => fake()->sha256()],
        ];
    }
}
