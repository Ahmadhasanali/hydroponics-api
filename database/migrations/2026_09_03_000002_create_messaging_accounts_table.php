<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 20)->default('telegram');
            $table->string('external_id');
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('default_farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->dateTime('linked_at');
            $table->timestamps();
            $table->unique(['channel', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_accounts');
    }
};
