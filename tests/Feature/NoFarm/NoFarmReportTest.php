<?php

namespace Tests\Feature\NoFarm;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NoFarmReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_no_farm_user_gets_empty_state_on_all_report_pages(): void
    {
        $user = User::factory()->create();

        $routes = [
            'reports.monitoring',
            'reports.nutrient',
            'reports.ph-down',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($user)->get(route($route));

            $response->assertOk();
            $response->assertViewIs('farm.no-farm');
        }
    }

    public function test_no_farm_user_gets_empty_state_on_activity_logs(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('activity-logs.index'));

        $response->assertOk();
        $response->assertViewIs('farm.no-farm');
    }
}
