<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\DailyMonitoringController;
use App\Models\Farm;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DailyMonitoringApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $owner;

    private Farm $farm;

    private Tank $tank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->farm = Farm::factory()->create(['created_by' => $this->owner->id]);
        $this->farm->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->tank = Tank::factory()->create([
            'farm_id' => $this->farm->id,
            'created_by' => $this->owner->id,
            'target_ppm_min' => 800,
            'target_ppm_max' => 1200,
            'target_ph_min' => 5.5,
            'target_ph_max' => 6.5,
        ]);

        Route::middleware(SubstituteBindings::class)->group(function () {
            Route::apiResource('monitoring', DailyMonitoringController::class);
        });
    }

    public function test_lists_monitoring_by_farm_id(): void
    {
        DailyMonitoring::factory()->create(['tank_id' => $this->tank->id, 'user_id' => $this->owner->id]);

        $response = $this->actingAs($this->owner)
            ->getJson('/monitoring?farm_id='.$this->farm->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_index_rejects_missing_farm_id(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/monitoring');

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_creates_monitoring(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/monitoring', [
                'tank_id' => $this->tank->id,
                'log_date' => '2026-08-01',
                'ppm' => 850,
                'ph' => 6.2,
                'water_temperature' => 24.5,
                'notes' => 'Normal',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.monitoring.ppm', '850.00');

        $this->assertDatabaseHas('daily_monitorings', [
            'tank_id' => $this->tank->id,
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_duplicate_tank_date_is_rejected(): void
    {
        $date = '2026-07-30';
        DailyMonitoring::factory()->create([
            'tank_id' => $this->tank->id,
            'user_id' => $this->owner->id,
            'log_date' => $date,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson('/monitoring', [
                'tank_id' => $this->tank->id,
                'log_date' => $date,
                'ppm' => 900,
                'ph' => 6.0,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.log_date.0', 'Monitoring untuk tank ini pada tanggal tersebut sudah ada.');

        $this->assertDatabaseCount('daily_monitorings', 1);
    }

    public function test_updates_monitoring(): void
    {
        $monitoring = DailyMonitoring::factory()->create([
            'tank_id' => $this->tank->id,
            'user_id' => $this->owner->id,
            'log_date' => '2026-08-01',
        ]);

        $response = $this->actingAs($this->owner)
            ->putJson('/monitoring/'.$monitoring->id, [
                'tank_id' => $this->tank->id,
                'log_date' => '2026-08-02',
                'ppm' => 950,
                'ph' => 6.0,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_deletes_monitoring(): void
    {
        $monitoring = DailyMonitoring::factory()->create([
            'tank_id' => $this->tank->id,
            'user_id' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson('/monitoring/'.$monitoring->id);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_create_returns_warnings_when_outside_target(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/monitoring', [
                'tank_id' => $this->tank->id,
                'log_date' => '2026-08-02',
                'ppm' => 500,
                'ph' => 7.5,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.warnings', function ($warnings) {
                return is_string($warnings) && strlen($warnings) > 0;
            });
    }

    public function test_shows_monitoring_detail(): void
    {
        $monitoring = DailyMonitoring::factory()->create([
            'tank_id' => $this->tank->id,
            'user_id' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/monitoring/'.$monitoring->id);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }
}
