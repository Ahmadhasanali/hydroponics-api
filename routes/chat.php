<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'throttle:10,1'])->group(function () {
    Route::post('/api/chat', ChatController::class)->name('chat.send');
});
