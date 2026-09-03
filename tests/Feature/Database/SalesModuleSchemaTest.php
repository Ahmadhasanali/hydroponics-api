<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesModuleSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sales_tables_exist_with_expected_columns(): void
    {
        foreach (['customers', 'products', 'accounts', 'sales', 'sale_items', 'payments', 'account_transfers', 'account_balance_adjustments', 'sale_financial_links'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "tabel {$table} tidak ada");
        }

        $this->assertTrue(Schema::hasColumns('customers', ['id', 'farm_id', 'name', 'phone', 'address', 'is_active']));
        $this->assertTrue(Schema::hasColumns('products', ['id', 'farm_id', 'name', 'unit', 'default_price', 'is_active']));
        $this->assertTrue(Schema::hasColumns('accounts', ['id', 'farm_id', 'name', 'type', 'balance_initial', 'is_default', 'is_active']));
        $this->assertTrue(Schema::hasColumns('sales', ['id', 'farm_id', 'customer_id', 'sale_date', 'due_date', 'total_amount', 'note', 'user_id', 'staff_id']));
        $this->assertTrue(Schema::hasColumns('sale_items', ['id', 'sale_id', 'product_id', 'product_name', 'unit', 'qty', 'price', 'subtotal']));
        $this->assertTrue(Schema::hasColumns('payments', ['id', 'sale_id', 'account_id', 'amount', 'payment_date', 'note']));
        $this->assertTrue(Schema::hasColumns('account_transfers', ['id', 'farm_id', 'from_account_id', 'to_account_id', 'amount', 'transfer_date', 'note']));
        $this->assertTrue(Schema::hasColumns('account_balance_adjustments', ['id', 'farm_id', 'account_id', 'amount', 'adjustment_date', 'reason']));
        $this->assertTrue(Schema::hasColumns('sale_financial_links', ['id', 'farm_id', 'financial_transaction_id', 'linkable_type', 'linkable_id']));
        $this->assertTrue(Schema::hasColumn('financial_transactions', 'account_id'));
    }
}
