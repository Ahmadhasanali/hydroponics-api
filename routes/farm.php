<?php

use App\Http\Controllers\Farm\FarmController;
use App\Http\Controllers\FarmUserController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth'], 'prefix' => 'farm', 'as' => 'farm.'], function () {
    Route::get('/', [FarmController::class, 'index'])->name('index');
    Route::get('/create', [FarmController::class, 'create'])->name('create');
    Route::post('/store', [FarmController::class, 'store'])->name('store');
    Route::get('/{farm}', [FarmController::class, 'show'])->name('show');
    Route::get('/{farm}/edit', [FarmController::class, 'edit'])->name('edit');
    Route::put('/{farm}', [FarmController::class, 'update'])->name('update');
    Route::delete('/{farm}', [FarmController::class, 'destroy'])->name('destroy');

    Route::get('/{farm}/members', [FarmUserController::class, 'index'])->name('members.index');
    Route::get('/{farm}/members/create', [FarmUserController::class, 'create'])->name('members.create');
    Route::post('/{farm}/members', [FarmUserController::class, 'store'])->name('members.store');
    Route::delete('/{farm}/members/{farmUser}', [FarmUserController::class, 'destroy'])->name('members.destroy');
});
