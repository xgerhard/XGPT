<?php

namespace App\Services\Socialite;

use SocialiteProviders\Manager\SocialiteWasCalled;

class NightbotExtend
{
    public function handle(SocialiteWasCalled $socialiteWasCalled)
    {
        $socialiteWasCalled->extendSocialite('nightbot', NightbotProvider::class);
    }
}