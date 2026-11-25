<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CommandController;
use App\Http\Middleware\CommandMiddleware;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/command', [CommandController::class, 'run'])->middleware(CommandMiddleware::class);