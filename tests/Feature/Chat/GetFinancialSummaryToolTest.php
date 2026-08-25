<?php

namespace Tests\Feature\Chat;

use App\ChatTools\GetFinancialSummaryTool;
use App\Models\Farm;
use App\Models\Farm\FinancialCategory;
use App\Models\Farm\FinancialTransaction;
use App\Models\User;
use App\Services\ChatToolsService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GetFinancialSummaryToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $owner;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->farm = Farm::factory()->create(['created_by' => $this->owner->id]);
        $this->farm->users()->attach($this->owner->id, ['role' => 'owner']);
    }

    private function addIncome(float $amount, ?string $date = null): void
    {
        FinancialTransaction::factory()->income()->create([
            'farm_id' => $this->farm->id,
            'category_id' => FinancialCategory::factory()->forFarm($this->farm->id)->income()->create()->id,
            'transaction_date' => $date ?? now()->toDateString(),
            'amount' => $amount,
        ]);
    }

    public function test_returns_current_month_summary_for_single_farm(): void
    {
        $this->addIncome(75000);

        $result = (new GetFinancialSummaryTool)->handle([], $this->owner);

        $this->assertArrayHasKey('data', $result);
        $this->assertSame($this->farm->id, $result['data']['farm_id']);
        $this->assertSame(75000.0, $result['data']['income']);
        $this->assertSame(75000.0, $result['data']['net']);
    }

    public function test_multiple_farms_return_per_farm_list(): void
    {
        $second = Farm::factory()->create();
        $this->owner->farms()->attach($second->id, ['role' => 'manager']);

        $result = (new GetFinancialSummaryTool)->handle([], $this->owner);

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(2, $result['data']);
    }

    public function test_rejects_inaccessible_farm(): void
    {
        $foreign = Farm::factory()->create();

        $result = (new GetFinancialSummaryTool)->handle(['farm_id' => $foreign->id], $this->owner);

        $this->assertArrayHasKey('error', $result);
    }

    public function test_rejects_non_numeric_farm_id(): void
    {
        $result = (new GetFinancialSummaryTool)->handle(['farm_id' => 'abc'], $this->owner);

        $this->assertArrayHasKey('error', $result);
    }

    public function test_rejects_negative_farm_id(): void
    {
        $result = (new GetFinancialSummaryTool)->handle(['farm_id' => -5], $this->owner);

        $this->assertArrayHasKey('error', $result);
    }

    public function test_last_month_period_uses_last_month_range(): void
    {
        $base = now()->subMonthNoOverflow();
        $this->addIncome(50000, $base->copy()->startOfMonth()->toDateString());
        $this->addIncome(75000);

        $result = (new GetFinancialSummaryTool)->handle(['period' => 'last_month'], $this->owner);

        $this->assertArrayHasKey('data', $result);
        $this->assertSame($base->copy()->startOfMonth()->toDateString(), $result['data']['from']);
        $this->assertSame($base->copy()->endOfMonth()->toDateString(), $result['data']['to']);
        $this->assertSame(50000.0, $result['data']['income']);
    }

    public function test_from_to_override_ignores_period(): void
    {
        $target = now()->subDays(3);
        $this->addIncome(20000, $target->toDateString());
        $this->addIncome(75000);

        $result = (new GetFinancialSummaryTool)->handle([
            'period' => 'last_month',
            'from' => $target->toDateString(),
            'to' => $target->toDateString(),
        ], $this->owner);

        $this->assertArrayHasKey('data', $result);
        $this->assertSame($target->toDateString(), $result['data']['from']);
        $this->assertSame($target->toDateString(), $result['data']['to']);
        $this->assertSame(20000.0, $result['data']['income']);
    }

    public function test_tool_is_auto_discovered_by_service(): void
    {
        $service = new ChatToolsService;

        $names = array_column($service->declarations(), 'name');

        $this->assertContains('get_financial_summary', $names);
    }
}
