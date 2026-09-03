<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Sales\AccountController;
use App\Models\Farm;
use App\Models\Farm\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AccountApiTest extends TestCase
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

        Route::prefix('api/v1')->middleware(SubstituteBindings::class)->group(function (): void {
            Route::get('accounts/{account}/balance', [AccountController::class, 'balance']);
            Route::get('accounts/{account}/adjustments', [AccountController::class, 'adjustments']);
            Route::post('accounts/{account}/adjustments', [AccountController::class, 'storeAdjustment']);
            Route::apiResource('accounts', AccountController::class)->only(['index', 'store', 'update', 'destroy']);
        });
    }

    public function test_store_creates_account(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/accounts', [
                'farm_id' => $this->farm->id,
                'name' => 'Dana',
                'type' => 'ewallet',
                'balance_initial' => 500000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.account.type', 'ewallet');
    }

    public function test_adjustment_updates_balance(): void
    {
        $account = Account::factory()->create(['farm_id' => $this->farm->id]);

        $this->actingAs($this->owner)
            ->postJson("/api/v1/accounts/{$account->id}/adjustments", [
                'amount' => 250000,
                'adjustment_date' => now()->toDateString(),
                'reason' => 'Topup dari ATM',
            ])
            ->assertCreated();

        $this->actingAs($this->owner)
            ->getJson("/api/v1/accounts/{$account->id}/balance")
            ->assertOk()
            ->assertJsonPath('data.balance', 250000);
    }

    public function test_default_account_cannot_be_deactivated(): void
    {
        $account = Account::factory()->cash()->create(['farm_id' => $this->farm->id]);

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/accounts/{$account->id}")
            ->assertStatus(422);
    }
}
