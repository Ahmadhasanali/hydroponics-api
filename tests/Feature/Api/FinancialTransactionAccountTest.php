<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\FinancialTransactionController;
use App\Models\Farm;
use App\Models\Farm\Account;
use App\Models\Farm\FinancialCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FinancialTransactionAccountTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $owner;

    private Farm $farm;

    private Account $dana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->farm = Farm::factory()->create();
        $this->farm->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->dana = Account::factory()->ewallet()->create(['farm_id' => $this->farm->id]);

        Route::prefix('api/v1')->middleware(SubstituteBindings::class)->group(function (): void {
            Route::apiResource('financial-transactions', FinancialTransactionController::class)
                ->parameters(['financial-transactions' => 'financialTransaction']);
        });
    }

    public function test_store_expense_with_account(): void
    {
        $category = FinancialCategory::factory()->forFarm($this->farm->id)->expense()->create();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/financial-transactions', [
                'farm_id' => $this->farm->id,
                'category_id' => $category->id,
                'type' => 'expense',
                'amount' => 50000,
                'transaction_date' => now()->toDateString(),
                'account_id' => $this->dana->id,
                'note' => 'Beli nutrisi',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('financial_transactions', [
            'farm_id' => $this->farm->id,
            'account_id' => $this->dana->id,
            'amount' => 50000,
        ]);
    }

    public function test_store_rejects_account_from_other_farm(): void
    {
        $category = FinancialCategory::factory()->forFarm($this->farm->id)->expense()->create();
        $otherFarmAccount = Account::factory()->create(); // farm lain

        $this->actingAs($this->owner)
            ->postJson('/api/v1/financial-transactions', [
                'farm_id' => $this->farm->id,
                'category_id' => $category->id,
                'type' => 'expense',
                'amount' => 50000,
                'transaction_date' => now()->toDateString(),
                'account_id' => $otherFarmAccount->id,
            ])
            ->assertStatus(422);
    }
}
