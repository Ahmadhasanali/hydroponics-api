<?php

namespace Tests\Feature\FarmActivity;

use App\Models\Farm;
use App\Models\Farm\Account;
use App\Models\Farm\Customer;
use App\Models\Farm\FinancialCategory;
use App\Models\Farm\Payment;
use App\Models\User;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SalesServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $user;

    private Farm $farm;

    private Customer $customer;

    private Account $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->farm = Farm::factory()->create();
        $this->farm->users()->attach($this->user->id, ['role' => 'owner']);
        $this->customer = Customer::factory()->create(['farm_id' => $this->farm->id]);
        $this->cash = Account::factory()->cash()->create(['farm_id' => $this->farm->id]);

        FinancialCategory::firstOrCreate(
            ['farm_id' => null, 'name' => 'Penjualan Panen', 'type' => 'income'],
            ['is_default' => true, 'is_active' => true],
        );
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'sale_date' => '2026-09-01',
            'due_date' => null,
            'note' => null,
            'items' => [
                ['product_name' => 'Selada', 'unit' => 'kg', 'qty' => 3, 'price' => 21000],
            ],
        ], $overrides);
    }

    public function test_credit_sale_creates_sale_without_financial_transaction(): void
    {
        $sale = app(SalesService::class)->createSale($this->user, $this->farm, $this->payload(['due_date' => '2026-09-08']));

        $this->assertSame('63000.00', (string) $sale->total_amount);
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'total_amount' => 63000]);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_cash_sale_creates_payment_and_income_transaction(): void
    {
        $sale = app(SalesService::class)->createSale($this->user, $this->farm, $this->payload([
            'account_id' => $this->cash->id,
            'amount_paid' => 63000,
        ]));

        $this->assertDatabaseHas('payments', ['sale_id' => $sale->id, 'account_id' => $this->cash->id, 'amount' => 63000]);
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertDatabaseHas('financial_transactions', [
            'farm_id' => $this->farm->id,
            'type' => 'income',
            'amount' => 63000,
            'source' => 'sale',
            'status' => 'approved',
            'account_id' => $this->cash->id,
        ]);
        $this->assertDatabaseHas('sale_financial_links', [
            'farm_id' => $this->farm->id,
            'linkable_type' => Payment::class,
        ]);
    }

    public function test_partial_payment_then_settle(): void
    {
        $service = app(SalesService::class);
        $sale = $service->createSale($this->user, $this->farm, $this->payload(['due_date' => '2026-09-08']));

        $service->registerPayment($this->user, $sale, [
            'account_id' => $this->cash->id,
            'amount' => 30000,
            'payment_date' => '2026-09-03',
        ]);
        $this->assertSame(30000.0, $service->paidAmount($sale));
        $this->assertSame('partial', $service->status($sale));
        $this->assertDatabaseCount('financial_transactions', 1);

        $service->registerPayment($this->user, $sale, [
            'account_id' => $this->cash->id,
            'amount' => 33000,
            'payment_date' => '2026-09-05',
        ]);
        $this->assertSame('paid', $service->status($sale->fresh()));
        $this->assertDatabaseCount('financial_transactions', 2);
    }

    public function test_overpayment_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $service = app(SalesService::class);
        $sale = $service->createSale($this->user, $this->farm, $this->payload(['due_date' => '2026-09-08']));
        $service->registerPayment($this->user, $sale, [
            'account_id' => $this->cash->id,
            'amount' => 70000,
            'payment_date' => '2026-09-03',
        ]);
    }
}
