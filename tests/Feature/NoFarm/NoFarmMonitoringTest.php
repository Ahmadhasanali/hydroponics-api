<?php

namespace Tests\Feature\NoFarm;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NoFarmMonitoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_no_farm_user_gets_empty_state_on_all_monitoring_pages(): void
    {
        $user = User::factory()->create();

        $routes = [
            'daily-monitoring.index',
            'daily-monitoring.create',
            'nutrient-addition.index',
            'nutrient-addition.create',
            'ph-down-log.index',
            'ph-down-log.create',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($user)->get(route($route));

            $response->assertOk();
            $response->assertViewIs('farm.no-farm');
        }
    }

    public function test_no_farm_user_cannot_store_monitoring_data(): void
    {
        $user = User::factory()->create();

        $post = $this->actingAs($user)->post(route('daily-monitoring.store'), [
            'tank_id' => 1,
            'log_date' => now()->toDateString(),
            'ppm' => 850,
            'ph' => 6.2,
        ]);
        $post->assertRedirect(route('farm.create'));

        $nutrient = $this->actingAs($user)->post(route('nutrient-addition.store'), [
            'tank_id' => 1,
            'log_date' => now()->toDateString(),
            'ppm_before' => 800,
            'ppm_after' => 900,
            'nutrient_a_ml' => 10,
            'nutrient_b_ml' => 10,
        ]);
        $nutrient->assertRedirect(route('farm.create'));

        $ph = $this->actingAs($user)->post(route('ph-down-log.store'), [
            'tank_id' => 1,
            'log_date' => now()->toDateString(),
            'ph_before' => 7.0,
            'ph_after' => 6.5,
            'ph_down_ml' => 5,
        ]);
        $ph->assertRedirect(route('farm.create'));

        $this->assertDatabaseCount('daily_monitorings', 0);
        $this->assertDatabaseCount('nutrient_additions', 0);
        $this->assertDatabaseCount('ph_down_logs', 0);
    }
}
