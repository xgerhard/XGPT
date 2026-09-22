<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSettings>
 */
class UserSettingsFactory extends Factory
{
    protected $model = UserSettings::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'instructions' => 'You are a helpful assistant for a Twitch chat. Answer in 250 characters or less.',
            'ai_provider' => null,
            'ai_api_key' => null,
            'mention_user' => true,
            'show_sponsor_heart' => true,
        ];
    }
}
