<?php

namespace App\src;

class Badges
{
    static function getBadge($provider, $providerId)
    {
        $badges = [
            'twitch' => [
                49056910 => '❤️',
                23038469 => '❤️'

            ],
            'discord' => [
                867661941446041601111 => '❤️'
            ]
        ];

        if (isset($badges[$provider][$providerId])) {
            return $badges[$provider][$providerId];
        }

        return false;
    }
}