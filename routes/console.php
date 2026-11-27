<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('xgpt:cleanup')->hourly();
Schedule::command('cache:clear conversations')->dailyAt('10:00');
