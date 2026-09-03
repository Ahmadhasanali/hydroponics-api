<?php

namespace Tests\Feature\Telegram;

use App\ChatTools\GetReceivablesTool;
use App\Models\Farm;
use App\Models\Farm\Customer;
use App\Models\Farm\Sale;
use App\Models\User;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GetReceivablesToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $owner;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->farm = Farm::factory()->create();
        $this->farm->users()->attach($this->owner->id, ['role' => 'owner']);
    }

    public function test_returns_summary_and_receivables(): void
    {
        $customer = Customer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Warung Bu Siti']);
        $paidCustomer = Customer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Warung Lunas']);

        // Piutang belum dibayar (kredit, tempo depan)
        Sale::factory()->create([
            'farm_id' => $this->farm->id,
            'customer_id' => $customer->id,
            'due_date' => now()->addDays(3)->toDateString(),
            'total_amount' => 100000,
            'sale_date' => now()->toDateString(),
        ]);

        // Piutang lunas
        $paidSale = Sale::factory()->create([
            'farm_id' => $this->farm->id,
            'customer_id' => $paidCustomer->id,
            'due_date' => now()->addDays(1)->toDateString(),
            'total_amount' => 50000,
            'sale_date' => now()->toDateString(),
        ]);
        // Bayar lunas via SalesService supaya status paid & remaining 0.
        app(SalesService::class)->registerPayment($this->owner, $paidSale, [
            'account_id' => \App\Models\Farm\Account::factory()->cash()->create(['farm_id' => $this->farm->id])->id,
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
        ]);

        $res = (new GetReceivablesTool)->handle(['farm_id' => $this->farm->id], $this->owner);

        $this->assertArrayHasKey('data', $res);
        $this->assertSame(100000.0, $res['data']['total_remaining']);
        $this->assertSame(1, $res['data']['customer_count']);
        $this->assertSame(0, $res['data']['overdue_count']);
        $this->assertCount(1, $res['data']['receivables']);
        $this->assertSame('Warung Bu Siti', $res['data']['receivables'][0]['customer']);
    }

    public function test_overdue_only_filter(): void
    {
        $customer = Customer::factory()->create(['farm_id' => $this->farm->id]);

        Sale::factory()->create([
            'farm_id' => $this->farm->id,
            'customer_id' => $customer->id,
            'due_date' => now()->subDays(2)->toDateString(),
            'total_amount' => 70000,
            'sale_date' => now()->subDays(10)->toDateString(),
        ]);

        $res = (new GetReceivablesTool)->handle(['farm_id' => $this->farm->id, 'overdue_only' => true], $this->owner);

        $this->assertArrayHasKey('data', $res);
        $this->assertSame(1, $res['data']['overdue_count']);
        $this->assertCount(1, $res['data']['receivables']);
        $this->assertTrue($res['data']['receivables'][0]['overdue']);
    }

    public function test_rejects_other_farm(): void
    {
        $otherFarm = Farm::factory()->create();
        $this->owner->farms()->attach($otherFarm->id, ['role' => 'owner']);

        $res = (new GetReceivablesTool)->handle(['farm_id' => 99999], $this->owner);

        $this->assertSame('Farm tidak ditemukan atau Anda tidak memiliki akses.', $res['error']);
    }
}
