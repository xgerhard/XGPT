<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Daily request counters, reset at midnight UTC: the app-wide free-tier ceiling and each
 * own-key channel's optional daily limit.
 */
class DailyRequestBudget
{
    /**
     * Record one request against $scope and report whether it's still within $limit for today.
     * Always increments (even once over) so this stays correct under concurrent requests without
     * a separate check-then-increment race.
     */
    public function consume(string $scope, int $limit): bool
    {
        $key = "xgpt:daily-requests:{$scope}:".now()->utc()->format('Y-m-d');

        Cache::add($key, 0, now()->utc()->endOfDay());

        return Cache::increment($key) <= $limit;
    }
}
