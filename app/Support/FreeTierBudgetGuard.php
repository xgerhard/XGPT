<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * A proactive daily ceiling on free-tier (site-key) requests, app-wide — distinct from the
 * per-channel/per-IP rate limiters, and from reactively catching the provider's own
 * insufficient-quota response. BYO-key channels never call this; it's only their own spend.
 */
class FreeTierBudgetGuard
{
    /**
     * Record one free-tier request and report whether it's still within today's budget. Always
     * increments (even once over) so this stays correct under concurrent requests without a
     * separate check-then-increment race.
     */
    public function consume(): bool
    {
        $key = $this->cacheKeyForToday();

        Cache::add($key, 0, now()->endOfDay());
        $count = Cache::increment($key);

        return $count <= (int) config('xgpt.free_tier.daily_request_limit');
    }

    private function cacheKeyForToday(): string
    {
        return 'xgpt:free-tier-requests:'.now()->utc()->format('Y-m-d');
    }
}
