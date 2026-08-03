<?php

namespace Tests\Feature\Staff;

use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffAttributionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_monitoring_actor_name_returns_staff_name(): void
    {
        $staff = Staff::factory()->create();
        $tank = Tank::factory()->create(['farm_id' => $staff->farm_id]);
        $monitoring = DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'staff_id' => $staff->id,
            'user_id' => null,
        ]);

        $this->assertSame($staff->name, $monitoring->actorName());
    }

    public function test_nutrient_actor_name_returns_user_name(): void
    {
        $user = User::factory()->create();
        $tank = Tank::factory()->create();
        $addition = NutrientAddition::factory()->create([
            'tank_id' => $tank->id,
            'user_id' => $user->id,
            'staff_id' => null,
        ]);

        $this->assertSame($user->name, $addition->actorName());
    }

    public function test_ph_down_log_staff_relation(): void
    {
        $staff = Staff::factory()->create();
        $tank = Tank::factory()->create(['farm_id' => $staff->farm_id]);
        $log = PhDownLog::factory()->create([
            'tank_id' => $tank->id,
            'staff_id' => $staff->id,
            'user_id' => null,
        ]);

        $this->assertTrue($log->staff->is($staff));
    }
}
