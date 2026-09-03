<?php

namespace Tests\Feature\Database;

use App\Models\Farm\Account;
use App\Models\Farm\Customer;
use App\Models\Farm\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SalesModuleModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_customer_product_account_creatable(): void
    {
        $customer = Customer::factory()->create(['name' => 'Warung Bu Siti']);
        $product = Product::factory()->create(['name' => 'Selada', 'default_price' => 21000]);
        $account = Account::factory()->create(['name' => 'Cash', 'type' => 'cash']);

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Warung Bu Siti']);
        $this->assertSame('21000.00', (string) $product->fresh()->default_price);
        $this->assertSame('cash', $account->fresh()->type);
    }
}
