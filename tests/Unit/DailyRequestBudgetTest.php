<?php

use App\Support\DailyRequestBudget;
use Carbon\Carbon;

it('allows requests within the daily limit', function () {
    $budget = new DailyRequestBudget;

    expect($budget->consume('test', 3))->toBeTrue()
        ->and($budget->consume('test', 3))->toBeTrue()
        ->and($budget->consume('test', 3))->toBeTrue();
});

it('rejects once the daily limit is exceeded', function () {
    $budget = new DailyRequestBudget;

    expect($budget->consume('test', 2))->toBeTrue()
        ->and($budget->consume('test', 2))->toBeTrue()
        ->and($budget->consume('test', 2))->toBeFalse()
        ->and($budget->consume('test', 2))->toBeFalse();
});

it('counts each scope separately', function () {
    $budget = new DailyRequestBudget;

    expect($budget->consume('channel:1', 1))->toBeTrue()
        ->and($budget->consume('channel:1', 1))->toBeFalse()
        ->and($budget->consume('channel:2', 1))->toBeTrue();
});

it('resets the budget at midnight UTC, whatever the app timezone', function () {
    config(['app.timezone' => 'America/New_York']);
    date_default_timezone_set('America/New_York');
    $budget = new DailyRequestBudget;

    Carbon::setTestNow(Carbon::parse('2026-01-01 23:59:00', 'UTC'));
    expect($budget->consume('test', 1))->toBeTrue()
        ->and($budget->consume('test', 1))->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-01-02 00:01:00', 'UTC'));
    expect($budget->consume('test', 1))->toBeTrue();

    Carbon::setTestNow();
    date_default_timezone_set('UTC');
});
