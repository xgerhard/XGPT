<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Models\Conversation;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('home');
});

Route::get('login', [AuthController::class, 'redirectToNightbot'])->name('login');
Route::get('login/nightbot/callback', [AuthController::class, 'handleNightbotCallback']);
Route::get('login/{platform}', [AuthController::class, 'redirectToPlatform'])->whereIn('platform', array_keys(AuthController::SCOPES));
Route::get('login/{platform}/callback', [AuthController::class, 'handlePlatformCallback'])->whereIn('platform', array_keys(AuthController::SCOPES));
Route::get('logout', [AuthController::class, 'logout']);

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::post('dashboard', [DashboardController::class, 'save']);
});

Route::get('/clean', function () {
    Conversation::where('created_at', '<', now()->subDay())->delete();
    echo ':)';
});