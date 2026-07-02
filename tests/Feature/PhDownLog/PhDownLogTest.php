<?php

namespace Tests\Feature\PhDownLog;

use App\Models\Farm;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PhDownLogTest extends TestCase
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

    /** @see FR #8 - pH Down Log: View history */
    public function test_user_can_view_ph_down_logs(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();
        PhDownLog::factory()->create(['tank_id' => $tank->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('ph-down-log.index'));

        $response->assertOk();
    }

    /** @see FR #8 - pH Down Log: Create log */
    public function test_user_can_create_ph_down_log(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();

        $response = $this->actingAs($user)->post(route('ph-down-log.store'), [
            'tank_id' => $tank->id,
            'log_date' => now()->toDateString(),
            'ph_before' => 7.5,
            'ph_after' => 6.0,
            'ph_down_ml' => 10,
        ]);

        $response->assertRedirect(route('ph-down-log.index'));
        $this->assertDatabaseHas('ph_down_logs', ['tank_id' => $tank->id, 'ph_before' => 7.5]);
    }

    /** @see FR #8 - pH Down Log: pH After < pH Before */
    public function test_ph_after_must_be_lower(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();

        $response = $this->actingAs($user)->post(route('ph-down-log.store'), [
            'tank_id' => $tank->id,
            'log_date' => now()->toDateString(),
            'ph_before' => 6.0,
            'ph_after' => 7.0,
        ]);

        $response->assertSessionHasErrors('ph_after');
    }

    /** @see FR #8 - pH Down Log: Edit log */
    public function test_user_can_update_ph_down_log(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();
        $log = PhDownLog::factory()->create(['tank_id' => $tank->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->put(route('ph-down-log.update', $log), [
            'tank_id' => $tank->id,
            'log_date' => now()->toDateString(),
            'ph_before' => 8.0,
            'ph_after' => 5.5,
            'ph_down_ml' => 20,
        ]);

        $response->assertRedirect(route('ph-down-log.index'));
        $this->assertDatabaseHas('ph_down_logs', ['id' => $log->id, 'ph_before' => 8.0]);
    }

    /** @see FR #8 - pH Down Log: Delete log */
    public function test_user_can_delete_ph_down_log(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();
        $log = PhDownLog::factory()->create(['tank_id' => $tank->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('ph-down-log.destroy', $log));

        $response->assertRedirect(route('ph-down-log.index'));
        $this->assertSoftDeleted($log);
    }
}
