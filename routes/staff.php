<?php

use App\Http\Controllers\Staff\StaffAuthController;
use App\Http\Controllers\Staff\StaffDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/staff/login', [StaffAuthController::class, 'showLoginForm'])->name('staff.login');
Route::post('/staff/login', [StaffAuthController::class, 'login'])
    ->middleware('throttle:staff-login')
    ->name('staff.login.attempt');
Route::post('/staff/logout', [StaffAuthController::class, 'logout'])
    ->middleware('auth:staff')
    ->name('staff.logout');

Route::middleware('auth:staff')->group(function () {
    Route::get('/staff', [StaffDashboardController::class, 'index'])->name('staff.dashboard');
});
