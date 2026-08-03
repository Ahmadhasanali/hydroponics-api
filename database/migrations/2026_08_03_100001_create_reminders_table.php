<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->morphs('created_by');
            $table->string('title');
            $table->text('body');
            $table->dateTime('starts_at');
            $table->json('recurrence')->nullable();
            $table->unsignedInteger('advance_notify_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('farm_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
