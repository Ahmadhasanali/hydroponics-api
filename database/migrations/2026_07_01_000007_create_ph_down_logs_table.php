<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ph_down_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('tank_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->decimal('ph_before', 5, 2);
            $table->decimal('ph_after', 5, 2);
            $table->decimal('ph_down_ml', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ph_down_logs');
    }
};
