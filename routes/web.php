<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NightbotAuthController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home');

Route::get('login/nightbot/callback', [NightbotAuthController::class, 'handleProviderCallback']);
Route::get('login', [NightbotAuthController::class, 'redirectToProvider'])->name('login');
Route::get('logout', [NightbotAuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('dashboard', [DashboardController::class, 'save'])->name('dashboard.save');
});
