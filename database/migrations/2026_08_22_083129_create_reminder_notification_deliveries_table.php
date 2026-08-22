<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('occurrence_id')->constrained('reminder_occurrences')->cascadeOnDelete();
            $table->morphs('notifiable');
            $table->string('kind', 20);
            $table->dateTime('sent_at');
            $table->dateTime('opened_at')->nullable();
            $table->timestamps();
            $table->index(['occurrence_id', 'notifiable_type', 'notifiable_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_notification_deliveries');
    }
};
