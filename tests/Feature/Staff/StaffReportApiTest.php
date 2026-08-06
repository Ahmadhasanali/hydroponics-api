<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffReportApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Farm $farm;

    private Staff $staff;

    private Tank $tank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->farm = Farm::factory()->create();
        $this->staff = Staff::factory()->create(['farm_id' => $this->farm->id]);
        $this->tank = Tank::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Tank A', 'created_by' => $this->farm->created_by]);
        Sanctum::actingAs($this->staff, ['staff']);
    }

    public function test_staff_can_view_monitoring_report(): void
    {
        DailyMonitoring::factory()->create(['tank_id' => $this->tank->id, 'log_date' => '2026-08-01', 'ppm' => 700, 'ph' => 6.0]);
        DailyMonitoring::factory()->create(['tank_id' => $this->tank->id, 'log_date' => '2026-08-02', 'ppm' => 900, 'ph' => 6.2]);

        $response = $this->getJson('/api/v1/staff/reports/monitoring?tank_id='.$this->tank->id.'&start_date=2026-08-01&end_date=2026-08-31');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.aggregates.count', 2)
            ->assertJsonPath('data.aggregates.avg_ppm', fn (mixed $avgPpm): bool => (float) $avgPpm === 800.0);
    }

    public function test_staff_monitoring_report_ignores_other_farm_tank(): void
    {
        $otherTank = Tank::factory()->create(['farm_id' => Farm::factory()->create()->id, 'name' => 'Tank Lain']);

        $this->getJson('/api/v1/staff/reports/monitoring?tank_id='.$otherTank->id.'&start_date=2026-08-01&end_date=2026-08-31')
            ->assertOk()
            ->assertJsonPath('data.aggregates', null);
    }
}
