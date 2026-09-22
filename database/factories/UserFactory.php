<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'display_name' => fake()->userName(),
            'provider' => 'twitch',
            'provider_id' => (string) fake()->unique()->numberBetween(1, 999999999),
            'token' => Str::random(8),
            'sponsor' => false,
        ];
    }
}
