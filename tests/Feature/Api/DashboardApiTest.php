<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\DashboardController;
use App\Models\Farm;
use App\Models\Farm\ActivityLog;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('api/v1/dashboard', [DashboardController::class, 'index']);
    }

    #[Test]
    public function user_with_no_farms_gets_empty_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.farms', [])
            ->assertJsonPath('data.selectedFarm', null)
            ->assertJsonPath('data.tanks', [])
            ->assertJsonPath('data.activityLogs', [])
            ->assertJsonPath('data.stats', []);
    }

    #[Test]
    public function user_with_farm_and_tanks_gets_dashboard_payload(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create([
            'created_by' => $user->id,
            'name' => 'Farm Utama',
        ]);
        $user->farms()->attach($farm, ['role' => 'manager']);

        $createdBy = $user->id;
        $farmId = $farm->id;

        Tank::create([
            'farm_id' => $farmId,
            'created_by' => $createdBy,
            'name' => 'Tangki A',
            'capacity_liter' => 1000,
            'is_active' => true,
            'current_ppm' => 100,
            'current_ph' => 6,
            'current_water_temperature' => 25,
        ]);

        Tank::create([
            'farm_id' => $farmId,
            'created_by' => $createdBy,
            'name' => 'Tangki B',
            'capacity_liter' => 500,
            'is_active' => false,
            'current_ppm' => 200,
            'current_ph' => 7,
            'current_water_temperature' => 27,
        ]);

        ActivityLog::create([
            'farm_id' => $farmId,
            'user_id' => $createdBy,
            'action' => 'sistem.pls',
            'entity_type' => 'management',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.farms')
            ->assertJsonPath('data.selectedFarm.id', $farm->id)
            ->assertJsonCount(2, 'data.tanks')
            ->assertJsonPath('data.stats.total_tanks', 2)
            ->assertJsonPath('data.stats.active_tanks', 1)
            ->assertJsonPath('data.stats.avg_ppm', 150)
            ->assertJsonPath('data.stats.avg_ph', 6.5)
            ->assertJsonPath('data.stats.avg_temp', 26)
            ->assertJsonCount(1, 'data.activityLogs');
    }
}