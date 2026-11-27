<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SettingsController;
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

Route::view('install', 'install');
Route::view('pricing', 'pricing');
Route::view('contact', 'contact');
Route::view('login', 'login')->name('login');
Route::get('login/nightbot', [AuthController::class, 'redirectToNightbot']);
Route::get('login/nightbot/callback', [AuthController::class, 'handleNightbotCallback']);
Route::get('login/{platform}', [AuthController::class, 'redirectToPlatform'])->whereIn('platform', array_keys(AuthController::SCOPES));
Route::get('login/{platform}/callback', [AuthController::class, 'handlePlatformCallback'])->whereIn('platform', array_keys(AuthController::SCOPES));
Route::get('logout', [AuthController::class, 'logout']);

Route::middleware(['auth'])->group(function () {
    Route::get('settings', [SettingsController::class, 'index']);
    Route::post('settings', [SettingsController::class, 'save']);
});
Route::redirect('dashboard', 'settings');

Route::get('/clean', function () {
    Conversation::where('created_at', '<', now()->subDay())->delete();
    echo ':)';
});