<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_financial_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_transaction_id')->constrained()->cascadeOnDelete();
            $table->morphs('linkable'); // Sale atau Payment
            $table->timestamps();
            $table->unique(['linkable_type', 'linkable_id', 'financial_transaction_id'], 'sale_financial_link_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_financial_links');
    }
};
