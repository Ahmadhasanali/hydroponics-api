<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_pending_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('messaging_account_id')->constrained('messaging_accounts')->cascadeOnDelete();
            $table->string('chat_id');
            $table->bigInteger('message_id')->nullable();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->date('sale_date');
            $table->date('due_date')->nullable();
            $table->json('items');
            $table->decimal('amount_paid', 12, 2)->nullable();
            $table->text('note')->nullable();
            $table->string('status', 30)->default('awaiting_confirm');
            $table->dateTime('expires_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['chat_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_pending_sales');
    }
};
