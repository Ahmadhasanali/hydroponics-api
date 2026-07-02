<?php

namespace Tests\Feature\DailyMonitoring;

use App\Models\Farm;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DailyMonitoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setUpFarm(): array
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        session()->put('selected_farm_id', $farm->id);
        $tank = Tank::factory()->create([
            'farm_id' => $farm->id,
            'created_by' => $user->id,
            'target_ppm_min' => 800,
            'target_ppm_max' => 1200,
            'target_ph_min' => 5.5,
            'target_ph_max' => 6.5,
        ]);

        return compact('user', 'farm', 'tank');
    }

    /** @see FR #6 - Daily Monitoring: View history */
    public function test_user_can_view_monitoring_list(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();
        DailyMonitoring::factory()->create(['tank_id' => $tank->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('daily-monitoring.index'));
        $response->assertOk();
    }

    /** @see FR #6 - Daily Monitoring: Create monitoring */
    public function test_user_can_create_monitoring(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();

        $response = $this->actingAs($user)->post(route('daily-monitoring.store'), [
            'tank_id' => $tank->id,
            'log_date' => now()->toDateString(),
            'ppm' => 850,
            'ph' => 6.2,
            'water_temperature' => 24.5,
            'notes' => 'Normal',
        ]);

        $response->assertRedirect(route('daily-monitoring.index'));
        $this->assertDatabaseHas('daily_monitorings', [
            'tank_id' => $tank->id,
            'ppm' => 850,
        ]);
    }

    /** @see FR #6 - Daily Monitoring: Unique per tank per day */
    public function test_monitoring_requires_unique_tank_date(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();
        DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'user_id' => $user->id,
            'log_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->post(route('daily-monitoring.store'), [
            'tank_id' => $tank->id,
            'log_date' => now()->toDateString(),
            'ppm' => 900,
            'ph' => 6.0,
        ]);

        $response->assertSessionHasErrors('log_date');
    }

    /** @see FR #6 - Daily Monitoring: PPM range validation */
    public function test_monitoring_ppm_range_validation(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();

        $response = $this->actingAs($user)->post(route('daily-monitoring.store'), [
            'tank_id' => $tank->id,
            'log_date' => now()->toDateString(),
            'ppm' => 3500,
            'ph' => 6.0,
        ]);

        $response->assertSessionHasErrors('ppm');
    }

    /** @see FR #6 - Daily Monitoring: pH range validation */
    public function test_monitoring_ph_range_validation(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();

        $response = $this->actingAs($user)->post(route('daily-monitoring.store'), [
            'tank_id' => $tank->id,
            'log_date' => now()->toDateString(),
            'ppm' => 800,
            'ph' => 15,
        ]);

        $response->assertSessionHasErrors('ph');
    }

    /** @see FR #6 - Daily Monitoring: Edit monitoring */
    public function test_user_can_update_monitoring(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();
        $monitoring = DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'user_id' => $user->id,
            'log_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->put(route('daily-monitoring.update', $monitoring), [
            'tank_id' => $tank->id,
            'log_date' => now()->toDateString(),
            'ppm' => 950,
            'ph' => 6.0,
        ]);

        $response->assertRedirect(route('daily-monitoring.index'));
        $this->assertDatabaseHas('daily_monitorings', ['id' => $monitoring->id, 'ppm' => 950]);
    }

    /** @see FR #6 - Daily Monitoring: Delete monitoring */
    public function test_user_can_delete_monitoring(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();
        $monitoring = DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->delete(route('daily-monitoring.destroy', $monitoring));

        $response->assertRedirect(route('daily-monitoring.index'));
        $this->assertSoftDeleted($monitoring);
    }

    /** @see FR #6 - Daily Monitoring: Target validation warning */
    public function test_monitoring_outside_target_shows_warning(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();

        $response = $this->actingAs($user)->post(route('daily-monitoring.store'), [
            'tank_id' => $tank->id,
            'log_date' => now()->toDateString(),
            'ppm' => 500,
            'ph' => 6.0,
        ]);

        $response->assertRedirect(route('daily-monitoring.index'));
        $response->assertSessionHas('warning');
    }
}
