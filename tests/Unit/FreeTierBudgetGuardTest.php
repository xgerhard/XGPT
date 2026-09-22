<?php

use App\Support\FreeTierBudgetGuard;
use Carbon\Carbon;

it('allows requests within the daily limit', function () {
    config(['xgpt.free_tier.daily_request_limit' => 3]);
    $guard = new FreeTierBudgetGuard;

    expect($guard->consume())->toBeTrue()
        ->and($guard->consume())->toBeTrue()
        ->and($guard->consume())->toBeTrue();
});

it('rejects once the daily limit is exceeded', function () {
    config(['xgpt.free_tier.daily_request_limit' => 2]);
    $guard = new FreeTierBudgetGuard;

    expect($guard->consume())->toBeTrue()
        ->and($guard->consume())->toBeTrue()
        ->and($guard->consume())->toBeFalse()
        ->and($guard->consume())->toBeFalse();
});

it('resets the budget on a new day', function () {
    config(['xgpt.free_tier.daily_request_limit' => 1]);
    $guard = new FreeTierBudgetGuard;

    Carbon::setTestNow('2026-01-01 23:59:00');
    expect($guard->consume())->toBeTrue()
        ->and($guard->consume())->toBeFalse();

    Carbon::setTestNow('2026-01-02 00:01:00');
    expect($guard->consume())->toBeTrue();

    Carbon::setTestNow();
});
