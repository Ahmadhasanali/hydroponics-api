<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setUpStaff(): array
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);
        $tank = Tank::factory()->create(['farm_id' => $farm->id]);

        return compact('farm', 'staff', 'tank');
    }

    public function test_staff_can_view_monitoring_report(): void
    {
        ['staff' => $staff, 'tank' => $tank] = $this->setUpStaff();
        DailyMonitoring::factory()->create(['tank_id' => $tank->id, 'ppm' => 700, 'ph' => 6.5, 'log_date' => '2026-08-01']);

        $response = $this->actingAs($staff, 'staff')->get(route('staff.reports.monitoring', [
            'tank_id' => $tank->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]));

        $response->assertOk();
        $response->assertSee('700');
    }

    public function test_staff_can_view_nutrient_report(): void
    {
        ['staff' => $staff, 'tank' => $tank] = $this->setUpStaff();
        NutrientAddition::factory()->create(['tank_id' => $tank->id, 'nutrient_a_ml' => 25, 'nutrient_b_ml' => 30, 'log_date' => '2026-08-01']);

        $response = $this->actingAs($staff, 'staff')->get(route('staff.reports.nutrient', [
            'tank_id' => $tank->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]));

        $response->assertOk();
        $response->assertSee('25.00');
    }

    public function test_staff_can_view_ph_down_report(): void
    {
        ['staff' => $staff, 'tank' => $tank] = $this->setUpStaff();
        PhDownLog::factory()->create(['tank_id' => $tank->id, 'ph_down_ml' => 12.5, 'log_date' => '2026-08-01']);

        $response = $this->actingAs($staff, 'staff')->get(route('staff.reports.ph-down', [
            'tank_id' => $tank->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]));

        $response->assertOk();
        $response->assertSee('12.50');
    }
}
