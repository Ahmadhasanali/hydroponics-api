<?php

namespace Tests\Unit\ChatTools;

use App\ChatTools\TankStatusTool;
use App\Models\Farm;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TankStatusToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function returns_current_tank_status(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        $tank = Tank::factory()->create([
            'farm_id' => $farm->id,
            'created_by' => $user->id,
            'current_ppm' => 850.5,
            'current_ph' => 6.2,
            'current_water_temperature' => 24.5,
        ]);

        $result = (new TankStatusTool())->handle(['tank_id' => $tank->id], $user);

        $this->assertArrayHasKey('data', $result);
        $this->assertSame($tank->id, $result['data']['id']);
        $this->assertSame('850.50', (string) $result['data']['current_ppm']);
        $this->assertSame('6.20', (string) $result['data']['current_ph']);
    }

    #[Test]
    public function returns_iso_timestamp_when_last_condition_is_a_plain_string(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        $tank = Tank::factory()->create([
            'farm_id' => $farm->id,
            'created_by' => $user->id,
        ]);
        $tank->forceFill(['last_condition_updated_at' => '2026-07-31 07:00:00'])->save();

        $result = (new TankStatusTool())->handle(['tank_id' => $tank->id], $user);

        $this->assertSame('2026-07-31T07:00:00+00:00', $result['data']['last_condition_updated_at']);
    }

    #[Test]
    public function returns_error_for_tank_outside_users_farms(): void
    {
        $user = User::factory()->create();

        $other = User::factory()->create();
        $otherFarm = Farm::factory()->create(['created_by' => $other->id]);
        $otherFarm->users()->attach($other->id, ['role' => 'owner']);
        $tank = Tank::factory()->create(['farm_id' => $otherFarm->id, 'created_by' => $other->id]);

        $result = (new TankStatusTool())->handle(['tank_id' => $tank->id], $user);

        $this->assertArrayHasKey('error', $result);
    }
}
