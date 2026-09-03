<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Sales\SaleController;
use App\Models\Farm;
use App\Models\Farm\Account;
use App\Models\Farm\Customer;
use App\Models\Farm\FinancialCategory;
use App\Models\Farm\Payment;
use App\Models\Farm\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SaleApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $owner;

    private Farm $farm;

    private Customer $customer;

    private Account $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->farm = Farm::factory()->create();
        $this->farm->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->customer = Customer::factory()->create(['farm_id' => $this->farm->id]);
        $this->cash = Account::factory()->cash()->create(['farm_id' => $this->farm->id]);

        FinancialCategory::firstOrCreate(
            ['farm_id' => null, 'name' => 'Penjualan Panen', 'type' => 'income'],
            ['is_default' => true, 'is_active' => true],
        );

        Route::prefix('api/v1')->middleware(SubstituteBindings::class)->group(function (): void {
            Route::get('sales/receivables/summary', [SaleController::class, 'receivableSummary']);
            Route::get('sales/receivables', [SaleController::class, 'receivables']);
            Route::apiResource('sales', SaleController::class);
        });
    }

    public function test_store_credit_sale(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/sales', [
                'farm_id' => $this->farm->id,
                'customer_id' => $this->customer->id,
                'sale_date' => '2026-09-01',
                'due_date' => '2026-09-08',
                'items' => [
                    ['product_name' => 'Selada', 'unit' => 'kg', 'qty' => 3, 'price' => 21000],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.sale.total_amount', '63000.00');
    }

    public function test_store_cash_sale_writes_income(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/sales', [
                'farm_id' => $this->farm->id,
                'customer_id' => $this->customer->id,
                'sale_date' => '2026-09-01',
                'items' => [
                    ['product_name' => 'Selada', 'unit' => 'kg', 'qty' => 3, 'price' => 21000],
                ],
                'account_id' => $this->cash->id,
                'amount_paid' => 63000,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('financial_transactions', [
            'farm_id' => $this->farm->id,
            'type' => 'income',
            'amount' => 63000,
            'source' => 'sale',
            'account_id' => $this->cash->id,
        ]);
    }

    public function test_receivables_lists_unpaid_only(): void
    {
        // Sale A: kredit belum dibayar → muncul di piutang
        Sale::factory()->credit()->create([
            'farm_id' => $this->farm->id,
            'customer_id' => $this->customer->id,
            'due_date' => now()->addDays(5)->toDateString(),
            'total_amount' => 63000,
        ]);
        // Sale B: kredit belum dibayar → muncul di piutang
        Sale::factory()->credit()->create([
            'farm_id' => $this->farm->id,
            'customer_id' => $this->customer->id,
            'due_date' => now()->addDays(3)->toDateString(),
            'total_amount' => 50000,
        ]);
        // Sale C: lunas penuh (ada payment) → TIDAK muncul di piutang
        $paidSale = Sale::factory()->credit()->create([
            'farm_id' => $this->farm->id,
            'customer_id' => $this->customer->id,
            'due_date' => now()->addDays(2)->toDateString(),
            'total_amount' => 20000,
        ]);
        Payment::factory()->create([
            'sale_id' => $paidSale->id,
            'account_id' => $this->cash->id,
            'amount' => 20000,
        ]);

        $this->actingAs($this->owner)
            ->getJson('/api/v1/sales/receivables?farm_id='.$this->farm->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }
}
