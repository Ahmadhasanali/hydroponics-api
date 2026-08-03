<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\ActivityLog;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffActivityLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_creation_logs_staff_id(): void
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);
        $tank = Tank::factory()->create(['farm_id' => $farm->id]);

        $this->actingAs($staff, 'staff')->post(route('staff.monitoring.store'), [
            'tank_id' => $tank->id,
            'log_date' => '2026-08-03',
            'ppm' => 700,
            'ph' => 6.5,
        ]);

        $log = ActivityLog::where('entity_type', 'daily_monitoring')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($staff->id, $log->staff_id);
        $this->assertNull($log->user_id);
    }
}
