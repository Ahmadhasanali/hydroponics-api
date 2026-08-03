<?php

use App\Http\Controllers\Farm\FarmController;
use App\Http\Controllers\Farm\FarmStaffController;
use App\Http\Controllers\FarmUserController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth', 'verified'], 'prefix' => 'farm', 'as' => 'farm.'], function () {
    Route::get('/', [FarmController::class, 'index'])->name('index');
    Route::get('/create', [FarmController::class, 'create'])->name('create');
    Route::post('/store', [FarmController::class, 'store'])->name('store');
    Route::get('/{farm}', [FarmController::class, 'show'])->name('show');
    Route::get('/{farm}/edit', [FarmController::class, 'edit'])->name('edit');
    Route::put('/{farm}', [FarmController::class, 'update'])->name('update');
    Route::delete('/{farm}', [FarmController::class, 'destroy'])->name('destroy');
    Route::post('/{farm}/transfer', [FarmController::class, 'transferOwnership'])->name('transfer');

    Route::get('/{farm}/members', [FarmUserController::class, 'index'])->name('members.index');
    Route::get('/{farm}/members/create', [FarmUserController::class, 'create'])->name('members.create');
    Route::post('/{farm}/members', [FarmUserController::class, 'store'])->name('members.store');
    Route::delete('/{farm}/members/{farmUser}', [FarmUserController::class, 'destroy'])->name('members.destroy');

    Route::get('/{farm}/members/staff/create', [FarmStaffController::class, 'create'])->name('members.staff-create');
    Route::post('/{farm}/members/staff', [FarmStaffController::class, 'store'])->name('members.staff-store');
    Route::put('/{farm}/members/staff/{staff}/password', [FarmStaffController::class, 'resetPassword'])->name('members.staff-password');
    Route::put('/{farm}/members/staff/{staff}/toggle', [FarmStaffController::class, 'toggle'])->name('members.staff-toggle');
    Route::delete('/{farm}/members/staff/{staff}', [FarmStaffController::class, 'destroy'])->name('members.staff-destroy');
});
