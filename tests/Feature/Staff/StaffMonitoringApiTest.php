<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffMonitoringApiTest extends TestCase
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

    public function test_staff_can_list_own_monitorings(): void
    {
        DailyMonitoring::factory()->create(['tank_id' => $this->tank->id, 'staff_id' => $this->staff->id, 'log_date' => '2026-08-01']);
        DailyMonitoring::factory()->create(['tank_id' => $this->tank->id, 'staff_id' => $this->staff->id, 'log_date' => '2026-08-02']);

        $this->getJson('/api/v1/staff/monitoring')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_staff_can_store_monitoring(): void
    {
        $response = $this->postJson('/api/v1/staff/monitoring', [
            'tank_id' => $this->tank->id,
            'log_date' => '2026-08-01',
            'ppm' => 800,
            'ph' => 6.2,
            'water_temperature' => 25.5,
            'notes' => 'Sehat',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.monitoring.staff_id', $this->staff->id)
            ->assertJsonPath('data.monitoring.user_id', null);
    }

    public function test_staff_cannot_store_monitoring_for_tank_of_another_farm(): void
    {
        $otherTank = Tank::factory()->create(['farm_id' => $this->otherFarm->id, 'name' => 'Tank Lain', 'created_by' => $this->otherFarm->created_by]);

        $this->postJson('/api/v1/staff/monitoring', [
            'tank_id' => $otherTank->id,
            'log_date' => '2026-08-01',
            'ppm' => 800,
            'ph' => 6.2,
        ])->assertStatus(403);
    }

    public function test_staff_cannot_create_duplicate_log_date_per_tank(): void
    {
        DailyMonitoring::factory()->create(['tank_id' => $this->tank->id, 'staff_id' => $this->staff->id, 'log_date' => '2026-08-01']);

        $this->postJson('/api/v1/staff/monitoring', [
            'tank_id' => $this->tank->id,
            'log_date' => '2026-08-01',
            'ppm' => 800,
            'ph' => 6.2,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_staff_can_update_own_monitoring(): void
    {
        $monitoring = DailyMonitoring::factory()->create(['tank_id' => $this->tank->id, 'staff_id' => $this->staff->id, 'log_date' => '2026-08-01']);

        $this->patchJson("/api/v1/staff/monitoring/{$monitoring->id}", [
            'tank_id' => $this->tank->id,
            'log_date' => '2026-08-01',
            'ppm' => 900,
            'ph' => 6.0,
        ])->assertOk()->assertJsonPath('data.monitoring.ppm', '900.00');
    }

    public function test_staff_cannot_update_monitoring_of_another_staff(): void
    {
        $otherStaff = Staff::factory()->create(['farm_id' => $this->farm->id]);
        $monitoring = DailyMonitoring::factory()->create(['tank_id' => $this->tank->id, 'staff_id' => $otherStaff->id, 'log_date' => '2026-08-01']);

        $this->patchJson("/api/v1/staff/monitoring/{$monitoring->id}", [
            'tank_id' => $this->tank->id,
            'log_date' => '2026-08-01',
            'ppm' => 900,
            'ph' => 6.0,
        ])->assertStatus(403);
    }

    public function test_staff_can_delete_own_monitoring(): void
    {
        $monitoring = DailyMonitoring::factory()->create(['tank_id' => $this->tank->id, 'staff_id' => $this->staff->id, 'log_date' => '2026-08-01']);

        $this->deleteJson("/api/v1/staff/monitoring/{$monitoring->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('daily_monitorings', ['id' => $monitoring->id]);
    }

    public function test_staff_monitoring_store_records_activity_log_with_staff_id(): void
    {
        $this->postJson('/api/v1/staff/monitoring', [
            'tank_id' => $this->tank->id,
            'log_date' => '2026-08-01',
            'ppm' => 800,
            'ph' => 6.2,
            'water_temperature' => 25.5,
            'notes' => 'Sehat',
        ])->assertStatus(201);

        $this->assertDatabaseHas('activity_logs', [
            'farm_id' => $this->farm->id,
            'action' => 'created',
            'entity_type' => 'daily_monitoring',
            'staff_id' => $this->staff->id,
            'user_id' => null,
        ]);
    }
}
