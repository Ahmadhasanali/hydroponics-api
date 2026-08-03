<?php

use App\Enums\ReminderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->dateTime('advance_notify_at')->nullable();
            $table->dateTime('advance_notified_at')->nullable();
            $table->dateTime('notified_at')->nullable();
            $table->string('status')->default(ReminderStatus::Pending->value);
            $table->nullableMorphs('completed_by');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['reminder_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_occurrences');
    }
};
