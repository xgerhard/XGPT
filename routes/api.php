<?php

use App\Http\Controllers\CommandController;
use App\Http\Middleware\DeliverOverflowMessage;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Public contract, do not change: bots (currently Nightbot) are already configured against
| GET /api/command?q=...&token=... in the wild.
|
*/

Route::get('/command', [CommandController::class, 'run'])
    ->middleware(['throttle:chat-command-ip', DeliverOverflowMessage::class]);
