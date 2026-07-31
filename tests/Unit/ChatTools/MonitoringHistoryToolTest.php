<?php

namespace Tests\Unit\ChatTools;

use App\ChatTools\MonitoringHistoryTool;
use App\Models\Farm;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MonitoringHistoryToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function ownedTank(): array
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);

        return [$user, $tank];
    }

    #[Test]
    public function returns_monitoring_records_within_days(): void
    {
        [$user, $tank] = $this->ownedTank();
        DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'user_id' => $user->id,
            'log_date' => now()->subDays(2)->toDateString(),
            'ppm' => 900,
            'ph' => 6.0,
        ]);
        DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'user_id' => $user->id,
            'log_date' => now()->subDays(30)->toDateString(),
            'ppm' => 700,
            'ph' => 6.4,
        ]);

        $result = (new MonitoringHistoryTool())->handle(['tank_id' => $tank->id, 'days' => 7], $user);

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertArrayHasKey('log_date', $result['data'][0]);
        $this->assertArrayHasKey('ppm', $result['data'][0]);
        $this->assertArrayHasKey('ph', $result['data'][0]);
    }

    #[Test]
    public function returns_error_for_foreign_tank(): void
    {
        $user = User::factory()->create();

        $other = User::factory()->create();
        $otherFarm = Farm::factory()->create(['created_by' => $other->id]);
        $otherFarm->users()->attach($other->id, ['role' => 'owner']);
        $tank = Tank::factory()->create(['farm_id' => $otherFarm->id, 'created_by' => $other->id]);

        $result = (new MonitoringHistoryTool())->handle(['tank_id' => $tank->id], $user);

        $this->assertArrayHasKey('error', $result);
    }
}
