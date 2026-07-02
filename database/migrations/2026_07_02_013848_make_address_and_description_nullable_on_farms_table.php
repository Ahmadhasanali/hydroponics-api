<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->string('address')->nullable()->change();
            $table->string('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->string('address')->nullable(false)->change();
            $table->string('description')->nullable(false)->change();
        });
    }
};
