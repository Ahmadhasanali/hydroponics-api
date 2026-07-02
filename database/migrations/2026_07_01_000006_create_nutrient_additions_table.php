<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrient_additions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('tank_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->decimal('ppm_before', 10, 2);
            $table->decimal('ppm_after', 10, 2);
            $table->decimal('nutrient_a_ml', 10, 2);
            $table->decimal('nutrient_b_ml', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrient_additions');
    }
};
