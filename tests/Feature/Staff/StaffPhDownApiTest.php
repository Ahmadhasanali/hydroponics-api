<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffPhDownApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Farm $farm;

    private Farm $otherFarm;

    private Staff $staff;

    private Tank $tank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->farm = Farm::factory()->create();
        $this->otherFarm = Farm::factory()->create();
        $this->staff = Staff::factory()->create(['farm_id' => $this->farm->id]);
        $this->tank = Tank::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Tank A', 'created_by' => $this->farm->created_by]);
        Sanctum::actingAs($this->staff, ['staff']);
    }

    public function test_staff_can_list_own_ph_down_logs(): void
    {
        PhDownLog::factory()->create(['tank_id' => $this->tank->id, 'staff_id' => $this->staff->id, 'log_date' => '2026-08-01']);
        PhDownLog::factory()->create(['tank_id' => $this->tank->id, 'staff_id' => $this->staff->id, 'log_date' => '2026-08-02']);

        $this->getJson('/api/v1/staff/ph-down')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_staff_can_store_ph_down_log(): void
    {
        $response = $this->postJson('/api/v1/staff/ph-down', [
            'tank_id' => $this->tank->id,
            'log_date' => '2026-08-01',
            'ph_before' => 7.5,
            'ph_after' => 6.5,
            'ph_down_ml' => 30,
            'notes' => 'Turunkan pH',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ph_down_log.staff_id', $this->staff->id);
    }

    public function test_staff_cannot_store_for_tank_of_another_farm(): void
    {
        $otherTank = Tank::factory()->create(['farm_id' => $this->otherFarm->id, 'name' => 'Tank Lain', 'created_by' => $this->otherFarm->created_by]);

        $this->postJson('/api/v1/staff/ph-down', [
            'tank_id' => $otherTank->id,
            'log_date' => '2026-08-01',
            'ph_before' => 7.5,
            'ph_after' => 6.5,
            'ph_down_ml' => 30,
        ])->assertStatus(403);
    }

    public function test_staff_can_update_own_ph_down_log(): void
    {
        $log = PhDownLog::factory()->create(['tank_id' => $this->tank->id, 'staff_id' => $this->staff->id, 'log_date' => '2026-08-01']);

        $this->patchJson("/api/v1/staff/ph-down/{$log->id}", [
            'tank_id' => $this->tank->id,
            'log_date' => '2026-08-01',
            'ph_before' => 7.5,
            'ph_after' => 6.0,
            'ph_down_ml' => 35,
        ])->assertOk()->assertJsonPath('data.ph_down_log.ph_after', '6.00');
    }

    public function test_staff_cannot_update_log_of_another_staff(): void
    {
        $otherStaff = Staff::factory()->create(['farm_id' => $this->farm->id]);
        $log = PhDownLog::factory()->create(['tank_id' => $this->tank->id, 'staff_id' => $otherStaff->id, 'log_date' => '2026-08-01']);

        $this->patchJson("/api/v1/staff/ph-down/{$log->id}", [
            'tank_id' => $this->tank->id,
            'log_date' => '2026-08-01',
            'ph_before' => 7.5,
            'ph_after' => 6.0,
            'ph_down_ml' => 35,
        ])->assertStatus(403);
    }

    public function test_staff_can_delete_own_ph_down_log(): void
    {
        $log = PhDownLog::factory()->create(['tank_id' => $this->tank->id, 'staff_id' => $this->staff->id, 'log_date' => '2026-08-01']);

        $this->deleteJson("/api/v1/staff/ph-down/{$log->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('ph_down_logs', ['id' => $log->id]);
    }
}
