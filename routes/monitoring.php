<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DailyMonitoringController;
use App\Http\Controllers\NutrientAdditionController;
use App\Http\Controllers\PhDownLogController;
use App\Http\Controllers\TankController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Tank
    Route::get('/tank', [TankController::class, 'index'])->name('tank.index');
    Route::get('/tank/create', [TankController::class, 'create'])->name('tank.create');
    Route::post('/tank/store', [TankController::class, 'store'])->name('tank.store');
    Route::get('/tank/{tank}', [TankController::class, 'show'])->name('tank.show');
    Route::get('/tank/{tank}/edit', [TankController::class, 'edit'])->name('tank.edit');
    Route::put('/tank/{tank}', [TankController::class, 'update'])->name('tank.update');
    Route::delete('/tank/{tank}', [TankController::class, 'destroy'])->name('tank.destroy');

    // Daily Monitoring
    Route::get('/daily-monitoring', [DailyMonitoringController::class, 'index'])->name('daily-monitoring.index');
    Route::get('/daily-monitoring/create', [DailyMonitoringController::class, 'create'])->name('daily-monitoring.create');
    Route::post('/daily-monitoring/store', [DailyMonitoringController::class, 'store'])->name('daily-monitoring.store');
    Route::get('/daily-monitoring/{dailyMonitoring}/edit', [DailyMonitoringController::class, 'edit'])->name('daily-monitoring.edit');
    Route::put('/daily-monitoring/{dailyMonitoring}', [DailyMonitoringController::class, 'update'])->name('daily-monitoring.update');
    Route::delete('/daily-monitoring/{dailyMonitoring}', [DailyMonitoringController::class, 'destroy'])->name('daily-monitoring.destroy');

    // Nutrient Addition (AB Mix)
    Route::get('/nutrient-addition', [NutrientAdditionController::class, 'index'])->name('nutrient-addition.index');
    Route::get('/nutrient-addition/create', [NutrientAdditionController::class, 'create'])->name('nutrient-addition.create');
    Route::post('/nutrient-addition/store', [NutrientAdditionController::class, 'store'])->name('nutrient-addition.store');
    Route::get('/nutrient-addition/{nutrientAddition}/edit', [NutrientAdditionController::class, 'edit'])->name('nutrient-addition.edit');
    Route::put('/nutrient-addition/{nutrientAddition}', [NutrientAdditionController::class, 'update'])->name('nutrient-addition.update');
    Route::delete('/nutrient-addition/{nutrientAddition}', [NutrientAdditionController::class, 'destroy'])->name('nutrient-addition.destroy');

    // pH Down Log
    Route::get('/ph-down-log', [PhDownLogController::class, 'index'])->name('ph-down-log.index');
    Route::get('/ph-down-log/create', [PhDownLogController::class, 'create'])->name('ph-down-log.create');
    Route::post('/ph-down-log/store', [PhDownLogController::class, 'store'])->name('ph-down-log.store');
    Route::get('/ph-down-log/{phDownLog}/edit', [PhDownLogController::class, 'edit'])->name('ph-down-log.edit');
    Route::put('/ph-down-log/{phDownLog}', [PhDownLogController::class, 'update'])->name('ph-down-log.update');
    Route::delete('/ph-down-log/{phDownLog}', [PhDownLogController::class, 'destroy'])->name('ph-down-log.destroy');

    // Activity Logs
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
});
