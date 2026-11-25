<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('xgpt:cleanup')->hourly();
