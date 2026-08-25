<?php

namespace Tests\Feature\FarmActivity;

use App\Models\Farm;
use App\Models\Farm\FinancialCategory;
use App\Models\Farm\FinancialTransaction;
use Database\Seeders\FinancialCategorySeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FinanceSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeds_eleven_global_categories(): void
    {
        $this->seed(FinancialCategorySeeder::class);

        $this->assertDatabaseCount('financial_categories', 11);
        $this->assertDatabaseHas('financial_categories', ['farm_id' => null, 'name' => 'Nutrisi (AB Mix)', 'type' => 'expense']);
        $this->assertDatabaseHas('financial_categories', ['farm_id' => null, 'name' => 'Penjualan Panen', 'type' => 'income']);
    }

    public function test_transaction_factory_persists_with_casts(): void
    {
        $tx = FinancialTransaction::factory()->expense()->create(['amount' => '150000.5']);

        $this->assertSame('150000.50', $tx->amount);
        $this->assertSame('expense', $tx->type);
        $this->assertSame('approved', $tx->status);
        $this->assertSame('manual', $tx->source);
        $this->assertInstanceOf(FinancialCategory::class, $tx->category);
        $this->assertSame($tx->farm_id, $tx->category->farm_id);
    }

    public function test_category_scope_for_farm_includes_global_and_own(): void
    {
        $farm = Farm::factory()->create();
        $own = FinancialCategory::factory()->forFarm($farm->id)->create(['name' => 'Custom A']);
        $otherFarm = Farm::factory()->create();
        FinancialCategory::factory()->forFarm($otherFarm->id)->create(['name' => 'Other Farm']);

        $names = FinancialCategory::forFarm($farm->id)->pluck('name');

        $this->assertTrue($names->contains('Custom A'));
        $this->assertFalse($names->contains('Other Farm'));
    }
}
