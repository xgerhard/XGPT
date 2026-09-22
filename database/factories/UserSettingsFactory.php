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
            'instructions' => null,  // optional custom flavor text, appended after ChatResponder's mandatory framing
            'ai_provider' => null,
            'ai_api_key' => null,
            'mention_user' => true,
            'show_sponsor_heart' => true,
        ];
    }
}
