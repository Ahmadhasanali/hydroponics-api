<?php

namespace Tests\Feature\Reminder;

use App\Models\Farm;
use App\Models\Farm\Account;
use App\Models\Farm\Customer;
use App\Models\Farm\Sale;
use App\Models\Reminder;
use App\Models\User;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SaleReminderTest extends TestCase
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
    }

    private function createCreditSale(): Sale
    {
        return app(SalesService::class)->createSale($this->user, $this->farm, [
            'customer_id' => $this->customer->id,
            'sale_date' => '2026-09-01',
            'due_date' => '2026-09-08',
            'items' => [
                ['product_name' => 'Selada', 'unit' => 'kg', 'qty' => 3, 'price' => 21000],
            ],
        ]);
    }

    public function test_credit_sale_creates_reminder_targeting_sale_and_owner(): void
    {
        $sale = $this->createCreditSale();

        $reminder = Reminder::query()
            ->whereHas('targets', fn ($q) => $q->where('targetable_type', Sale::class)->where('targetable_id', $sale->id))
            ->first();

        $this->assertNotNull($reminder);
        $this->assertSame('2026-09-08', $reminder->starts_at->toDateString());
        $this->assertTrue($reminder->is_active);

        $ownerTargeted = $reminder->targets()
            ->where('targetable_type', User::class)
            ->where('targetable_id', $this->user->id)
            ->exists();
        $this->assertTrue($ownerTargeted);
    }

    public function test_full_payment_marks_occurrence_done(): void
    {
        $sale = $this->createCreditSale();

        app(SalesService::class)->registerPayment($this->user, $sale, [
            'account_id' => $this->cash->id,
            'amount' => 63000,
            'payment_date' => '2026-09-05',
        ]);

        $reminder = Reminder::query()
            ->whereHas('targets', fn ($q) => $q->where('targetable_type', Sale::class)->where('targetable_id', $sale->id))
            ->firstOrFail();

        $this->assertDatabaseHas('reminder_occurrences', [
            'reminder_id' => $reminder->id,
            'status' => 'done',
        ]);
    }

    public function test_cancel_sale_deactivates_reminder(): void
    {
        $sale = $this->createCreditSale();

        app(SalesService::class)->cancelSale($this->user, $sale);

        $reminder = Reminder::query()
            ->withTrashed()
            ->whereHas('targets', fn ($q) => $q->where('targetable_type', Sale::class)->where('targetable_id', $sale->id))
            ->firstOrFail();

        $this->assertFalse($reminder->is_active);
    }
}
