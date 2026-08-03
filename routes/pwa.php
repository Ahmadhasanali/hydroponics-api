<?php

use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])
        ->name('push-subscriptions.store');

    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])
        ->name('push-subscriptions.destroy');
});

Route::middleware('auth:staff')->group(function () {
    Route::post('/staff/push-subscriptions', [PushSubscriptionController::class, 'store'])
        ->name('staff.push-subscriptions.store');

    Route::delete('/staff/push-subscriptions', [PushSubscriptionController::class, 'destroy'])
        ->name('staff.push-subscriptions.destroy');
});
