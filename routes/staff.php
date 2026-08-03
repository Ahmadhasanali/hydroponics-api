<?php

use App\Http\Controllers\Staff\StaffAuthController;
use App\Http\Controllers\Staff\StaffDashboardController;
use App\Http\Controllers\Staff\StaffMonitoringController;
use App\Http\Controllers\Staff\StaffNutrientAdditionController;
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

    Route::get('/staff/monitoring', [StaffMonitoringController::class, 'index'])->name('staff.monitoring.index');
    Route::get('/staff/monitoring/create', [StaffMonitoringController::class, 'create'])->name('staff.monitoring.create');
    Route::post('/staff/monitoring/store', [StaffMonitoringController::class, 'store'])->name('staff.monitoring.store');
    Route::get('/staff/monitoring/{dailyMonitoring}/edit', [StaffMonitoringController::class, 'edit'])->name('staff.monitoring.edit');
    Route::put('/staff/monitoring/{dailyMonitoring}', [StaffMonitoringController::class, 'update'])->name('staff.monitoring.update');
    Route::delete('/staff/monitoring/{dailyMonitoring}', [StaffMonitoringController::class, 'destroy'])->name('staff.monitoring.destroy');

    Route::get('/staff/nutrient', [StaffNutrientAdditionController::class, 'index'])->name('staff.nutrient.index');
    Route::get('/staff/nutrient/create', [StaffNutrientAdditionController::class, 'create'])->name('staff.nutrient.create');
    Route::post('/staff/nutrient/store', [StaffNutrientAdditionController::class, 'store'])->name('staff.nutrient.store');
    Route::get('/staff/nutrient/{nutrientAddition}/edit', [StaffNutrientAdditionController::class, 'edit'])->name('staff.nutrient.edit');
    Route::put('/staff/nutrient/{nutrientAddition}', [StaffNutrientAdditionController::class, 'update'])->name('staff.nutrient.update');
    Route::delete('/staff/nutrient/{nutrientAddition}', [StaffNutrientAdditionController::class, 'destroy'])->name('staff.nutrient.destroy');
});
