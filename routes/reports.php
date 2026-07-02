<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/reports/monitoring', [ReportController::class, 'monitoring'])->name('reports.monitoring');
    Route::get('/reports/nutrient', [ReportController::class, 'nutrient'])->name('reports.nutrient');
    Route::get('/reports/ph-down', [ReportController::class, 'phDown'])->name('reports.ph-down');
});
