<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('sale_date');
            $table->date('due_date')->nullable();
            $table->decimal('total_amount', 12, 2);
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['farm_id', 'sale_date']);
            $table->index(['farm_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
