<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\FinancialTransactionController;
use App\Models\Farm;
use App\Models\Farm\FinancialCategory;
use App\Models\Farm\FinancialTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FinancialTransactionApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $owner;

    private Farm $farm;

    private FinancialCategory $expenseCategory;

    private FinancialCategory $incomeCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->farm = Farm::factory()->create();
        $this->farm->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->expenseCategory = FinancialCategory::factory()->forFarm($this->farm->id)->expense()->create();
        $this->incomeCategory = FinancialCategory::factory()->forFarm($this->farm->id)->income()->create();

        Route::prefix('api/v1')->middleware(SubstituteBindings::class)->group(function (): void {
            Route::apiResource('financial-transactions', FinancialTransactionController::class)
                ->parameters(['financial-transactions' => 'financialTransaction']);
        });
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'farm_id' => $this->farm->id,
            'category_id' => $this->expenseCategory->id,
            'type' => 'expense',
            'amount' => 25000,
            'transaction_date' => '2026-08-20',
            'note' => 'Beli nutrisi',
        ], $overrides);
    }

    public function test_store_creates_manual_approved_transaction(): void
    {
        $response = $this->actingAs($this->owner)->postJson('/api/v1/financial-transactions', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.transaction.source', 'manual')
            ->assertJsonPath('data.transaction.status', 'approved');

        $this->assertDatabaseHas('financial_transactions', [
            'farm_id' => $this->farm->id,
            'amount' => 25000,
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_store_rejects_future_date(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/financial-transactions', $this->payload(['transaction_date' => now()->addDay()->toDateString()]))
            ->assertStatus(422);
    }

    public function test_store_rejects_type_category_mismatch(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/financial-transactions', $this->payload(['category_id' => $this->incomeCategory->id]))
            ->assertStatus(422);
    }

    public function test_store_rejects_zero_amount(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/financial-transactions', $this->payload(['amount' => 0]))
            ->assertStatus(422);
    }

    public function test_index_requires_farm_id_and_filters(): void
    {
        FinancialTransaction::factory()->expense()->create([
            'farm_id' => $this->farm->id,
            'category_id' => $this->expenseCategory->id,
            'transaction_date' => '2026-08-10',
            'note' => 'listrik bulanan',
        ]);
        FinancialTransaction::factory()->income()->create([
            'farm_id' => $this->farm->id,
            'category_id' => $this->incomeCategory->id,
            'transaction_date' => '2026-08-11',
        ]);

        $this->actingAs($this->owner)->getJson('/api/v1/financial-transactions')->assertStatus(422);

        $filtered = $this->actingAs($this->owner)
            ->getJson('/api/v1/financial-transactions?farm_id='.$this->farm->id.'&type=expense&search=listrik');

        $filtered->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_other_farm_data_is_isolated(): void
    {
        $otherOwner = User::factory()->create();
        $otherFarm = Farm::factory()->create();
        $otherFarm->users()->attach($otherOwner->id, ['role' => 'owner']);
        $otherTx = FinancialTransaction::factory()->expense()->create([
            'farm_id' => $otherFarm->id,
        ]);

        $this->actingAs($this->owner)
            ->getJson('/api/v1/financial-transactions?farm_id='.$this->farm->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/financial-transactions/{$otherTx->id}")
            ->assertForbidden();
    }

    public function test_update_and_soft_delete(): void
    {
        $tx = FinancialTransaction::factory()->expense()->create([
            'farm_id' => $this->farm->id,
            'category_id' => $this->expenseCategory->id,
        ]);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/financial-transactions/{$tx->id}", $this->payload(['amount' => 99000]))
            ->assertOk()
            ->assertJsonPath('data.transaction.amount', '99000.00');

        $this->actingAs($this->owner)->deleteJson("/api/v1/financial-transactions/{$tx->id}")->assertOk();

        $this->assertSoftDeleted('financial_transactions', ['id' => $tx->id]);
    }

    public function test_update_cannot_move_transaction_to_another_farm(): void
    {
        $tx = FinancialTransaction::factory()->expense()->create([
            'farm_id' => $this->farm->id,
            'category_id' => $this->expenseCategory->id,
        ]);
        $otherFarm = Farm::factory()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/financial-transactions/{$tx->id}", $this->payload(['farm_id' => $otherFarm->id]))
            ->assertOk()
            ->assertJsonPath('data.transaction.amount', '25000.00');

        $this->assertDatabaseHas('financial_transactions', [
            'id' => $tx->id,
            'farm_id' => $this->farm->id,
        ]);
    }

    public function test_store_rejects_category_from_other_farm(): void
    {
        $otherFarm = Farm::factory()->create();
        $otherCategory = FinancialCategory::factory()->forFarm($otherFarm->id)->expense()->create();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/financial-transactions', $this->payload(['category_id' => $otherCategory->id]))
            ->assertStatus(422);
    }

    public function test_store_rejects_inactive_category(): void
    {
        $inactiveCategory = FinancialCategory::factory()
            ->forFarm($this->farm->id)
            ->expense()
            ->create(['is_active' => false]);

        $this->actingAs($this->owner)
            ->postJson('/api/v1/financial-transactions', $this->payload(['category_id' => $inactiveCategory->id]))
            ->assertStatus(422);
    }
}
