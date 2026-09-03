<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('category_id')->constrained('accounts')->nullOnDelete();
        });

        DB::statement('ALTER TABLE financial_transactions DROP CONSTRAINT financial_transactions_source_check');
        DB::statement("ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_source_check CHECK (source IN ('manual', 'telegram', 'sale'))");
    }

    public function down(): void
    {
        // Order: drop CHECK last-reserved? Actually FK must be dropped before CHECK? Original dropped CHECK first then FK — risky.
        // Fixed: drop FK first, then CHECK. Use IF EXISTS for idempotency.
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });

        // Idempotent: only if constraint exists (IF EXISTS not valid for CHECK, guard via try)
        DB::statement('ALTER TABLE financial_transactions DROP CONSTRAINT IF EXISTS financial_transactions_source_check');
        DB::statement("ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_source_check CHECK (source IN ('manual', 'telegram'))");
    }
};
