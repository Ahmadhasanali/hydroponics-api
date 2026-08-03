<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffMonitoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setUpStaff(): array
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);
        $tank = Tank::factory()->create(['farm_id' => $farm->id]);

        return compact('farm', 'staff', 'tank');
    }

    public function test_staff_can_create_monitoring(): void
    {
        ['staff' => $staff, 'tank' => $tank] = $this->setUpStaff();

        $response = $this->actingAs($staff, 'staff')->post(route('staff.monitoring.store'), [
            'tank_id' => $tank->id,
            'log_date' => '2026-08-03',
            'ppm' => 700,
            'ph' => 6.5,
            'water_temperature' => 25,
        ]);

        $response->assertRedirect(route('staff.monitoring.index'));
        $this->assertDatabaseHas('daily_monitorings', [
            'tank_id' => $tank->id,
            'staff_id' => $staff->id,
            'user_id' => null,
        ]);
    }

    public function test_staff_cannot_use_tank_of_other_farm(): void
    {
        ['staff' => $staff] = $this->setUpStaff();
        $otherFarm = Farm::factory()->create();
        $otherTank = Tank::factory()->create(['farm_id' => $otherFarm->id]);

        $response = $this->actingAs($staff, 'staff')->post(route('staff.monitoring.store'), [
            'tank_id' => $otherTank->id,
            'log_date' => '2026-08-03',
            'ppm' => 700,
            'ph' => 6.5,
        ]);

        $response->assertForbidden();
    }

    public function test_staff_can_edit_own_monitoring(): void
    {
        ['staff' => $staff, 'tank' => $tank] = $this->setUpStaff();
        $monitoring = DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'staff_id' => $staff->id,
            'user_id' => null,
            'log_date' => '2026-08-03',
        ]);

        $response = $this->actingAs($staff, 'staff')->put(route('staff.monitoring.update', $monitoring), [
            'tank_id' => $tank->id,
            'log_date' => '2026-08-03',
            'ppm' => 800,
            'ph' => 6.6,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('daily_monitorings', ['id' => $monitoring->id, 'ppm' => 800]);
    }

    public function test_staff_cannot_edit_others_monitoring(): void
    {
        ['farm' => $farm, 'staff' => $staff, 'tank' => $tank] = $this->setUpStaff();
        $otherStaff = Staff::factory()->create(['farm_id' => $farm->id]);
        $monitoring = DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'staff_id' => $otherStaff->id,
            'user_id' => null,
        ]);

        $response = $this->actingAs($staff, 'staff')->put(route('staff.monitoring.update', $monitoring), [
            'tank_id' => $tank->id,
            'log_date' => '2026-08-03',
            'ppm' => 800,
            'ph' => 6.6,
        ]);

        $response->assertForbidden();
    }

    public function test_staff_can_delete_own_monitoring(): void
    {
        ['staff' => $staff, 'tank' => $tank] = $this->setUpStaff();
        $monitoring = DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'staff_id' => $staff->id,
            'user_id' => null,
        ]);

        $response = $this->actingAs($staff, 'staff')->delete(route('staff.monitoring.destroy', $monitoring));

        $response->assertRedirect();
        $this->assertSoftDeleted('daily_monitorings', ['id' => $monitoring->id]);
    }
}
