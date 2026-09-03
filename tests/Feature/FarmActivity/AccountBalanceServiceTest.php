<?php

namespace Tests\Feature\FarmActivity;

use App\Models\Farm;
use App\Models\Farm\Account;
use App\Models\Farm\AccountBalanceAdjustment;
use App\Models\Farm\AccountTransfer;
use App\Models\Farm\Customer;
use App\Models\Farm\FinancialCategory;
use App\Models\Farm\FinancialTransaction;
use App\Models\Farm\Payment;
use App\Models\Farm\Sale;
use App\Services\AccountBalanceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AccountBalanceServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Farm $farm;

    private Account $cash;

    private Account $dana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->farm = Farm::factory()->create();
        $this->cash = Account::factory()->cash()->create(['farm_id' => $this->farm->id, 'balance_initial' => 100000]);
        $this->dana = Account::factory()->ewallet()->create(['farm_id' => $this->farm->id]);
    }

    public function test_balance_combines_all_movements(): void
    {
        $customer = Customer::factory()->create(['farm_id' => $this->farm->id]);
        $sale = Sale::factory()->create([
            'farm_id' => $this->farm->id,
            'customer_id' => $customer->id,
            'total_amount' => 63000,
        ]);
        Payment::factory()->create([
            'sale_id' => $sale->id,
            'account_id' => $this->dana->id,
            'amount' => 63000,
        ]);

        $category = FinancialCategory::factory()->forFarm($this->farm->id)->expense()->create();
        FinancialTransaction::factory()->expense()->create([
            'farm_id' => $this->farm->id,
            'category_id' => $category->id,
            'amount' => 15000,
            'account_id' => $this->dana->id,
        ]);

        AccountTransfer::factory()->create([
            'farm_id' => $this->farm->id,
            'from_account_id' => $this->dana->id,
            'to_account_id' => $this->cash->id,
            'amount' => 20000,
        ]);

        AccountBalanceAdjustment::factory()->create([
            'farm_id' => $this->farm->id,
            'account_id' => $this->dana->id,
            'amount' => 5000,
        ]);

        $service = app(AccountBalanceService::class);

        $this->assertEquals(100000 + 20000, $service->balance($this->cash)); // awal + transfer masuk
        $this->assertEquals(63000 - 15000 - 20000 + 5000, $service->balance($this->dana));
    }
}
