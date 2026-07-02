<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_monitorings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('tank_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->decimal('ppm', 10, 2);
            $table->decimal('ph', 5, 2);
            $table->decimal('water_temperature', 5, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_monitorings');
    }
};
