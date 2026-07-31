<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'throttle:10,1'])->group(function () {
    Route::post('/api/chat', ChatController::class)->name('chat.send');

    Route::get('/api/chat/sessions', [ChatSessionController::class, 'index'])->name('chat.sessions.index');
    Route::post('/api/chat/sessions', [ChatSessionController::class, 'store'])->name('chat.sessions.store');
    Route::post('/api/chat/sessions/migrate', [ChatSessionController::class, 'migrate'])->name('chat.sessions.migrate');
    Route::get('/api/chat/sessions/{session}/messages', [ChatSessionController::class, 'messages'])->name('chat.sessions.messages');
    Route::patch('/api/chat/sessions/{session}', [ChatSessionController::class, 'update'])->name('chat.sessions.update');
    Route::delete('/api/chat/sessions/{session}', [ChatSessionController::class, 'destroy'])->name('chat.sessions.destroy');
});
