<?php

namespace App\src;

use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\UserSettings;

class SettingsHandler
{
    private $startInstructions =  'You are a helpful assistant for a <provider> chat.';
    private $endInstructions = 'Answer in 250 characters or less.';
    private $showConversationId = 1;
    private $mentionUser = 1;
    private $showSponsorHeart = 1;

    public function getUserWithSettings($provider, $providerId)
    {
        $user = User::where([
            'provider' => $provider,
            'provider_id' => $providerId
        ])->first();

        if ($user && $user->settings()->exists()) {
            return $user;
        } else {
            return $this->getDefaultSettings($provider);
        }
    }

    public function getDefaultSettings($provider)
    {
        $user = new User();
        $user->settings = new UserSettings([
            'api_key' => '',
            'start_instructions' => $this->getStartInstructions($provider),
            'end_instructions' => $this->endInstructions,
            'show_conversation_id' => $this->showConversationId,
            'mention_user' => $this->mentionUser,
            'show_sponsor_heart' => $this->showSponsorHeart
        ]);
        return $user;
    }

    public function setDefaultSettings(User $user)
    {
        $user->settings()->create([
            'user_id' => $user->id,
            'start_instructions' => $this->getStartInstructions($user->provider),
            'end_instructions' => $this->endInstructions,
            'show_conversation_id' => $this->showConversationId,
            'mention_user' => $this->mentionUser,
            'show_sponsor_heart' => $this->showSponsorHeart
        ]);
    }

    private function getStartInstructions($provider)
    {
        return str_replace('<provider>', ucfirst($provider), $this->startInstructions);
    }
}