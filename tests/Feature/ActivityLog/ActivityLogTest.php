<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Farm;
use App\Models\Farm\ActivityLog;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @see FR #11 - Activity Logs: Auto-record farm creation */
    public function test_activity_log_created_on_farm_creation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('farm.store'), [
            'name' => 'Farm Test',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'entity_type' => 'farm',
            'action' => 'created',
        ]);
    }

    /** @see FR #11 - Activity Logs: Auto-record tank creation */
    public function test_activity_log_created_on_tank_creation(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        session()->put('selected_farm_id', $farm->id);

        $this->actingAs($user)->post(route('tank.store'), [
            'name' => 'Tank Test',
            'capacity_liter' => 100,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'entity_type' => 'tank',
            'action' => 'created',
        ]);
    }

    /** @see FR #11 - Activity Logs: Auto-record monitoring creation */
    public function test_activity_log_created_on_monitoring_creation(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        session()->put('selected_farm_id', $farm->id);
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);

        $this->actingAs($user)->post(route('daily-monitoring.store'), [
            'tank_id' => $tank->id,
            'log_date' => now()->toDateString(),
            'ppm' => 800,
            'ph' => 6.0,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'entity_type' => 'daily_monitoring',
            'action' => 'created',
        ]);
    }

    /** @see FR #11 - Activity Logs: View activity logs */
    public function test_user_can_view_activity_logs(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        session()->put('selected_farm_id', $farm->id);
        ActivityLog::create([
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'action' => 'created',
            'entity_type' => 'tank',
            'entity_id' => 1,
            'description' => 'created tank',
        ]);

        $response = $this->actingAs($user)->get(route('activity-logs.index'));

        $response->assertOk();
    }
}
