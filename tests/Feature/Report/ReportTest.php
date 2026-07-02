<?php

namespace Tests\Feature\Report;

use App\Models\Farm;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setUpFarm(): array
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        session()->put('selected_farm_id', $farm->id);
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);

        return compact('user', 'farm', 'tank');
    }

    /** @see FR #10 - Reports: Monitoring report */
    public function test_user_can_view_monitoring_report(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();
        DailyMonitoring::factory(3)->create(['tank_id' => $tank->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('reports.monitoring', [
            'tank_id' => $tank->id,
            'date_from' => now()->subMonth()->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
    }

    /** @see FR #10 - Reports: Nutrient usage report */
    public function test_user_can_view_nutrient_report(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();
        NutrientAddition::factory(3)->create(['tank_id' => $tank->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('reports.nutrient', [
            'tank_id' => $tank->id,
            'date_from' => now()->subMonth()->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
    }

    /** @see FR #10 - Reports: pH Down usage report */
    public function test_user_can_view_ph_down_report(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();
        PhDownLog::factory(3)->create(['tank_id' => $tank->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('reports.ph-down', [
            'tank_id' => $tank->id,
            'date_from' => now()->subMonth()->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
    }
}
