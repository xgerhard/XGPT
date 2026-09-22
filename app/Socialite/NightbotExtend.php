<?php

namespace App\Socialite;

use SocialiteProviders\Manager\SocialiteWasCalled;

class NightbotExtend
{
    public function handle(SocialiteWasCalled $socialiteWasCalled): void
    {
        $socialiteWasCalled->extendSocialite('nightbot', NightbotProvider::class);
    }
}
