<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffPhDownTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setUpStaff(): array
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);
        $tank = Tank::factory()->create(['farm_id' => $farm->id]);

        return compact('farm', 'staff', 'tank');
    }

    public function test_staff_can_create_ph_down_log(): void
    {
        ['staff' => $staff, 'tank' => $tank] = $this->setUpStaff();

        $response = $this->actingAs($staff, 'staff')->post(route('staff.ph-down.store'), [
            'tank_id' => $tank->id,
            'log_date' => '2026-08-03',
            'ph_before' => 7.5,
            'ph_after' => 6.5,
            'ph_down_ml' => 20,
        ]);

        $response->assertRedirect(route('staff.ph-down.index'));
        $this->assertDatabaseHas('ph_down_logs', [
            'tank_id' => $tank->id,
            'staff_id' => $staff->id,
            'user_id' => null,
            'ph_after' => 6.5,
        ]);
    }

    public function test_staff_cannot_use_tank_of_other_farm(): void
    {
        ['staff' => $staff] = $this->setUpStaff();
        $otherFarm = Farm::factory()->create();
        $otherTank = Tank::factory()->create(['farm_id' => $otherFarm->id]);

        $response = $this->actingAs($staff, 'staff')->post(route('staff.ph-down.store'), [
            'tank_id' => $otherTank->id,
            'log_date' => '2026-08-03',
            'ph_before' => 7.5,
            'ph_after' => 6.5,
            'ph_down_ml' => 20,
        ]);

        $response->assertForbidden();
    }

    public function test_staff_can_edit_own_ph_down_log(): void
    {
        ['staff' => $staff, 'tank' => $tank] = $this->setUpStaff();
        $log = PhDownLog::factory()->create([
            'tank_id' => $tank->id,
            'staff_id' => $staff->id,
            'user_id' => null,
            'log_date' => '2026-08-03',
        ]);

        $response = $this->actingAs($staff, 'staff')->put(route('staff.ph-down.update', $log), [
            'tank_id' => $tank->id,
            'log_date' => '2026-08-03',
            'ph_before' => 7.8,
            'ph_after' => 6.8,
            'ph_down_ml' => 25,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ph_down_logs', ['id' => $log->id, 'ph_after' => 6.8]);
    }

    public function test_staff_cannot_edit_others_ph_down_log(): void
    {
        ['farm' => $farm, 'staff' => $staff, 'tank' => $tank] = $this->setUpStaff();
        $otherStaff = Staff::factory()->create(['farm_id' => $farm->id]);
        $log = PhDownLog::factory()->create([
            'tank_id' => $tank->id,
            'staff_id' => $otherStaff->id,
            'user_id' => null,
        ]);

        $response = $this->actingAs($staff, 'staff')->put(route('staff.ph-down.update', $log), [
            'tank_id' => $tank->id,
            'log_date' => '2026-08-03',
            'ph_before' => 7.8,
            'ph_after' => 6.8,
            'ph_down_ml' => 25,
        ]);

        $response->assertForbidden();
    }

    public function test_staff_can_delete_own_ph_down_log(): void
    {
        ['staff' => $staff, 'tank' => $tank] = $this->setUpStaff();
        $log = PhDownLog::factory()->create([
            'tank_id' => $tank->id,
            'staff_id' => $staff->id,
            'user_id' => null,
        ]);

        $response = $this->actingAs($staff, 'staff')->delete(route('staff.ph-down.destroy', $log));

        $response->assertRedirect();
        $this->assertSoftDeleted('ph_down_logs', ['id' => $log->id]);
    }
}
