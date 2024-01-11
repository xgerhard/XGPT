<?php

namespace App\src;

use App\Models\User;

class Badges
{
    static function getBadge($provider, $providerId)
    {
        // WIP: These need to be moved to DB
        $badges = [
            'twitch' => [
                49056910125 => '❤️',
                23038469 => '❤️'
            ],
            'discord' => [
                867661941446041601111 => '❤️'
            ]
        ];

        if (isset($badges[$provider][$providerId])) {
            return $badges[$provider][$providerId];
        }
        // End WIP

        $user = User::where([
            'provider' => $provider,
            'provider_id' => $providerId
        ])->first();

        if ($user && $user->sponsor) {
            if ($user->settings()->exists() && !$user->settings->show_sponsor_heart) {
                return false;
            }
            return '❤️';
        }
        return false;
    }
}