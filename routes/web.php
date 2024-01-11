<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\NightbotAuthController;
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

Route::get('login/nightbot/callback', [NightbotAuthController::class, 'handleProviderCallback']);
Route::get('login', [NightbotAuthController::class, 'redirectToProvider'])->name('login');
Route::get('logout', [NightbotAuthController::class, 'logout']);

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::post('dashboard', [DashboardController::class, 'save']);
});

Route::get('/clean', function () {
    Conversation::where('created_at', '<', now()->subDay())->delete();
    echo ':)';
});