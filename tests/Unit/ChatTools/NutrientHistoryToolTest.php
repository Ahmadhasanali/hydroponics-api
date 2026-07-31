<?php

namespace Tests\Unit\ChatTools;

use App\ChatTools\NutrientHistoryTool;
use App\Models\Farm;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NutrientHistoryToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function returns_nutrient_addition_records(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);
        NutrientAddition::factory()->create([
            'tank_id' => $tank->id,
            'user_id' => $user->id,
            'log_date' => now()->subDays(1)->toDateString(),
            'ppm_before' => 500,
            'ppm_after' => 900,
            'nutrient_a_ml' => 100,
            'nutrient_b_ml' => 100,
        ]);

        $result = (new NutrientHistoryTool())->handle(['tank_id' => $tank->id], $user);

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertArrayHasKey('ppm_before', $result['data'][0]);
        $this->assertArrayHasKey('ppm_after', $result['data'][0]);
        $this->assertArrayHasKey('nutrient_a_ml', $result['data'][0]);
        $this->assertArrayHasKey('nutrient_b_ml', $result['data'][0]);
    }

    #[Test]
    public function returns_error_for_foreign_tank(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherFarm = Farm::factory()->create(['created_by' => $other->id]);
        $otherFarm->users()->attach($other->id, ['role' => 'owner']);
        $tank = Tank::factory()->create(['farm_id' => $otherFarm->id, 'created_by' => $other->id]);

        $result = (new NutrientHistoryTool())->handle(['tank_id' => $tank->id], $user);

        $this->assertArrayHasKey('error', $result);
    }
}
