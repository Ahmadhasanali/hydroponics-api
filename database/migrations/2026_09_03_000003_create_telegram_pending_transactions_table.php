<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_pending_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('messaging_account_id')->constrained('messaging_accounts')->cascadeOnDelete();
            $table->string('chat_id');
            $table->bigInteger('message_id')->nullable();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->string('type', 20);
            $table->foreignId('category_id')->nullable()->constrained('financial_categories')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date');
            $table->text('note')->nullable();
            $table->string('status', 30)->default('awaiting_confirm');
            $table->dateTime('expires_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['chat_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_pending_transactions');
    }
};
