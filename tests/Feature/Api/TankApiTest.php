<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\TankController;
use App\Models\Farm;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TankApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $owner;

    private User $outsider;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->outsider = User::factory()->create();
        $this->farm = Farm::factory()->create(['created_by' => $this->owner->id]);
        $this->farm->users()->attach($this->owner->id, ['role' => 'owner']);

        Route::middleware(SubstituteBindings::class)->group(function () {
            Route::apiResource('tanks', TankController::class);
        });
    }

    public function test_lists_tanks_by_farm_id(): void
    {
        Tank::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Tank A', 'created_by' => $this->owner->id]);
        Tank::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Tank B', 'created_by' => $this->owner->id]);

        $response = $this->actingAs($this->owner)
            ->getJson('/tanks?farm_id='.$this->farm->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.tanks');
    }

    public function test_index_rejects_missing_farm_id(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/tanks');

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_creates_a_tank(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/tanks', [
                'farm_id' => $this->farm->id,
                'name' => 'Tank A1',
                'capacity_liter' => 100,
                'notes' => 'Tank utama',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tank.name', 'Tank A1');

        $this->assertDatabaseHas('tanks', [
            'name' => 'Tank A1',
            'farm_id' => $this->farm->id,
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_tank_name_must_be_unique_within_farm(): void
    {
        Tank::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Tank A1']);

        $response = $this->actingAs($this->owner)
            ->postJson('/tanks', [
                'farm_id' => $this->farm->id,
                'name' => 'Tank A1',
                'capacity_liter' => 200,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.name.0', 'Nama tank sudah digunakan di farm ini.');
    }

    public function test_shows_tank_detail(): void
    {
        $tank = Tank::factory()->create([
            'farm_id' => $this->farm->id,
            'name' => 'Tank Detail',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/tanks/'.$tank->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tank.name', 'Tank Detail');
    }

    public function test_updates_a_tank(): void
    {
        $tank = Tank::factory()->create([
            'farm_id' => $this->farm->id,
            'name' => 'Old Name',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->putJson('/tanks/'.$tank->id, [
                'name' => 'New Name',
                'capacity_liter' => 200,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('tanks', ['id' => $tank->id, 'name' => 'New Name']);
    }

    public function test_update_rejects_duplicate_name_within_farm(): void
    {
        Tank::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Taken Name', 'created_by' => $this->owner->id]);
        $tank = Tank::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Other Tank', 'created_by' => $this->owner->id]);

        $response = $this->actingAs($this->owner)
            ->putJson('/tanks/'.$tank->id, [
                'name' => 'Taken Name',
                'capacity_liter' => 200,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.name.0', 'Nama tank sudah digunakan di farm ini.');
    }

    public function test_soft_deletes_a_tank(): void
    {
        $tank = Tank::factory()->create([
            'farm_id' => $this->farm->id,
            'name' => 'To Delete',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson('/tanks/'.$tank->id);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted($tank);
    }

    public function test_update_denied_for_outsider(): void
    {
        $tank = Tank::factory()->create([
            'farm_id' => $this->farm->id,
            'name' => 'Protected Tank',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->outsider)
            ->putJson('/tanks/'.$tank->id, [
                'name' => 'Hacked',
                'capacity_liter' => 999,
            ]);

        $response->assertForbidden();
    }

    public function test_destroy_denied_for_outsider(): void
    {
        $tank = Tank::factory()->create([
            'farm_id' => $this->farm->id,
            'name' => 'Protected Tank',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->outsider)
            ->deleteJson('/tanks/'.$tank->id);

        $response->assertForbidden();
        $this->assertNotSoftDeleted($tank);
    }
}
