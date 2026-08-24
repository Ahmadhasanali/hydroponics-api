<?php

namespace Tests\Feature\FarmActivity;

use App\Models\Farm;
use App\Models\Farm\FinancialCategory;
use App\Models\Farm\FinancialTransaction;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FinanceServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Farm $farm;

    private FinancialCategory $expenseCategory;

    private FinancialCategory $incomeCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->farm = Farm::factory()->create();
        $this->expenseCategory = FinancialCategory::factory()->forFarm($this->farm->id)->expense()->create();
        $this->incomeCategory = FinancialCategory::factory()->forFarm($this->farm->id)->income()->create([
            'name' => 'Penjualan Panen',
        ]);
    }

    private function addExpense(string $date, float $amount, ?FinancialCategory $category = null): FinancialTransaction
    {
        return FinancialTransaction::factory()->expense()->create([
            'farm_id' => $this->farm->id,
            'category_id' => ($category ?? $this->expenseCategory)->id,
            'transaction_date' => $date,
            'amount' => $amount,
        ]);
    }

    public function test_totals_net_and_category_breakdown(): void
    {
        $this->addExpense('2026-08-01', 100000);
        $this->addExpense('2026-08-02', 50000, $this->expenseCategory);
        FinancialTransaction::factory()->income()->create([
            'farm_id' => $this->farm->id,
            'category_id' => $this->incomeCategory->id,
            'transaction_date' => '2026-08-03',
            'amount' => 400000,
        ]);

        $summary = app(FinanceService::class)->summary(
            $this->farm,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
        );

        $this->assertSame(400000.0, $summary['income']);
        $this->assertSame(150000.0, $summary['expense']);
        $this->assertSame(250000.0, $summary['net']);
        $this->assertCount(3, $summary['series']);
        $this->assertSame('Penjualan Panen', $summary['categories'][0]['category']);
    }

    public function test_monthly_grouping_buckets_by_month_start(): void
    {
        $this->addExpense('2026-07-15', 20000);
        $this->addExpense('2026-07-20', 30000);

        $summary = app(FinanceService::class)->summary(
            $this->farm,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
            'month',
        );

        $this->assertCount(1, $summary['series']);
        $this->assertSame('2026-07-01', $summary['series'][0]['period']);
        $this->assertSame(50000.0, $summary['series'][0]['expense']);
    }

    public function test_soft_deleted_and_pending_are_excluded(): void
    {
        $this->addExpense('2026-08-05', 70000)->delete();
        FinancialTransaction::factory()->expense()->pending()->create([
            'farm_id' => $this->farm->id,
            'category_id' => $this->expenseCategory->id,
            'transaction_date' => '2026-08-06',
            'amount' => 9000,
        ]);

        $summary = app(FinanceService::class)->summary(
            $this->farm,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
        );

        $this->assertSame(0.0, $summary['expense']);
    }
}
