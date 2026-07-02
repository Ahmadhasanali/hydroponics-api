<?php

use App\Http\Controllers\Farm\FarmController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth'], 'prefix' => 'farm', 'as' => 'farm.'], function () {
    Route::get('/', [FarmController::class, 'index'])->name('index');
    Route::get('/create', [FarmController::class, 'create'])->name('create');
    Route::post('/store', [FarmController::class, 'store'])->name('store');
    Route::get('/{farm}', [FarmController::class, 'show'])->name('show');
    Route::get('/{farm}/edit', [FarmController::class, 'edit'])->name('edit');
    Route::put('/{farm}', [FarmController::class, 'update'])->name('update');
    Route::delete('/{farm}', [FarmController::class, 'destroy'])->name('destroy');
});
