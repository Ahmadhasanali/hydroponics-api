<?php

namespace Tests\Unit\ChatTools;

use App\ChatTools\PhDownHistoryTool;
use App\Models\Farm;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhDownHistoryToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function returns_ph_down_records(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);
        PhDownLog::factory()->create([
            'tank_id' => $tank->id,
            'user_id' => $user->id,
            'log_date' => now()->subDays(1)->toDateString(),
            'ph_before' => 6.8,
            'ph_after' => 6.2,
            'ph_down_ml' => 50,
        ]);

        $result = (new PhDownHistoryTool())->handle(['tank_id' => $tank->id], $user);

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertArrayHasKey('ph_before', $result['data'][0]);
        $this->assertArrayHasKey('ph_after', $result['data'][0]);
        $this->assertArrayHasKey('ph_down_ml', $result['data'][0]);
    }

    #[Test]
    public function returns_error_for_foreign_tank(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherFarm = Farm::factory()->create(['created_by' => $other->id]);
        $otherFarm->users()->attach($other->id, ['role' => 'owner']);
        $tank = Tank::factory()->create(['farm_id' => $otherFarm->id, 'created_by' => $other->id]);

        $result = (new PhDownHistoryTool())->handle(['tank_id' => $tank->id], $user);

        $this->assertArrayHasKey('error', $result);
    }
}
