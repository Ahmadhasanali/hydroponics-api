<?php

use App\Http\Controllers\ReminderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('farm')->as('farm.')->group(function () {
    Route::get('/{farm}/reminders', [ReminderController::class, 'index'])->name('reminders.index');
    Route::get('/{farm}/reminders/create', [ReminderController::class, 'create'])->name('reminders.create');
    Route::post('/{farm}/reminders', [ReminderController::class, 'store'])->name('reminders.store');
    Route::get('/{farm}/reminders/calendar', [ReminderController::class, 'calendar'])->name('reminders.calendar');

    Route::get('/{farm}/reminders/{reminder}', [ReminderController::class, 'show'])->name('reminders.show');
    Route::get('/{farm}/reminders/{reminder}/edit', [ReminderController::class, 'edit'])->name('reminders.edit');
    Route::put('/{farm}/reminders/{reminder}', [ReminderController::class, 'update'])->name('reminders.update');
    Route::delete('/{farm}/reminders/{reminder}', [ReminderController::class, 'destroy'])->name('reminders.destroy');

    Route::post('/{farm}/reminders/occurrences/{occurrence}/done', [ReminderController::class, 'occurrenceDone'])->name('reminders.occurrence-done');
    Route::post('/{farm}/reminders/occurrences/{occurrence}/skip', [ReminderController::class, 'occurrenceSkip'])->name('reminders.occurrence-skip');
});
