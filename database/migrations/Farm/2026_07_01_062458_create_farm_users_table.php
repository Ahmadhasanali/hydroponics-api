<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('farm_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->index();
            $table->foreignId('user_id')->index();
            $table->enum('role', ['owner', 'manager', 'operator']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farm_users');
    }
};
