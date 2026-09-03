<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Sales\CustomerController;
use App\Models\Farm;
use App\Models\Farm\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CustomerApiTest extends TestCase
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
            Route::apiResource('customers', CustomerController::class)->only(['index', 'store', 'update', 'destroy']);
        });
    }

    public function test_store_creates_customer(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers', [
                'farm_id' => $this->farm->id,
                'name' => 'Warung Bu Siti',
                'phone' => '08123456',
                'address' => 'Pasar Pagi',
            ])
            ->assertCreated()
            ->assertJsonPath('data.customer.name', 'Warung Bu Siti');

        $this->assertDatabaseHas('customers', ['farm_id' => $this->farm->id, 'name' => 'Warung Bu Siti']);
    }

    public function test_index_scoped_to_farm(): void
    {
        Customer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Warung A']);
        Customer::factory()->create(['name' => 'Warung B']); // farm lain

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?farm_id='.$this->farm->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.customers');
    }

    public function test_destroy_deactivates(): void
    {
        $customer = Customer::factory()->create(['farm_id' => $this->farm->id]);

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertOk();

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'is_active' => false]);
    }

    public function test_member_cannot_manage_customer(): void
    {
        $member = User::factory()->create();
        $this->farm->users()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->postJson('/api/v1/customers', ['farm_id' => $this->farm->id, 'name' => 'X'])
            ->assertForbidden();
    }
}
