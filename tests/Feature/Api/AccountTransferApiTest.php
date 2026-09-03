<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Sales\AccountTransferController;
use App\Models\Farm;
use App\Models\Farm\Account;
use App\Models\Farm\AccountTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AccountTransferApiTest extends TestCase
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
            Route::apiResource('account-transfers', AccountTransferController::class)->only(['index', 'store', 'destroy']);
        });
    }

    public function test_store_transfer_between_accounts(): void
    {
        $cash = Account::factory()->cash()->create(['farm_id' => $this->farm->id]);
        $dana = Account::factory()->ewallet()->create(['farm_id' => $this->farm->id]);

        $this->actingAs($this->owner)
            ->postJson('/api/v1/account-transfers', [
                'farm_id' => $this->farm->id,
                'from_account_id' => $cash->id,
                'to_account_id' => $dana->id,
                'amount' => 50000,
                'transfer_date' => now()->toDateString(),
                'note' => 'Setor tunai',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('account_transfers', [
            'from_account_id' => $cash->id,
            'to_account_id' => $dana->id,
            'amount' => 50000,
        ]);
    }

    public function test_same_account_transfer_rejected(): void
    {
        $cash = Account::factory()->cash()->create(['farm_id' => $this->farm->id]);

        $this->actingAs($this->owner)
            ->postJson('/api/v1/account-transfers', [
                'farm_id' => $this->farm->id,
                'from_account_id' => $cash->id,
                'to_account_id' => $cash->id,
                'amount' => 50000,
                'transfer_date' => now()->toDateString(),
            ])
            ->assertStatus(422);
    }

    public function test_destroy_deletes_transfer(): void
    {
        $transfer = AccountTransfer::factory()->create(['farm_id' => $this->farm->id]);

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/account-transfers/{$transfer->id}")
            ->assertOk();

        $this->assertSoftDeleted('account_transfers', ['id' => $transfer->id]);
    }
}
