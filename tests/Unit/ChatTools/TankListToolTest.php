<?php

namespace Tests\Unit\ChatTools;

use App\ChatTools\TankListTool;
use App\Models\Farm;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TankListToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function ownerWithFarm(): array
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);

        return [$user, $farm];
    }

    #[Test]
    public function lists_all_accessible_tanks(): void
    {
        [$user, $farm] = $this->ownerWithFarm();
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);

        $other = User::factory()->create();
        $otherFarm = Farm::factory()->create(['created_by' => $other->id]);
        $otherFarm->users()->attach($other->id, ['role' => 'owner']);
        Tank::factory()->create(['farm_id' => $otherFarm->id, 'created_by' => $other->id]);

        $result = (new TankListTool)->handle([], $user);

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertSame($tank->name, $result['data'][0]['name']);
        $this->assertSame($farm->id, $result['data'][0]['farm_id']);
    }

    #[Test]
    public function filters_by_farm_id(): void
    {
        [$user, $farm] = $this->ownerWithFarm();
        $farmB = Farm::factory()->create(['created_by' => $user->id]);
        $farmB->users()->attach($user->id, ['role' => 'owner']);

        Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);
        $tankB = Tank::factory()->create(['farm_id' => $farmB->id, 'created_by' => $user->id]);

        $result = (new TankListTool)->handle(['farm_id' => $farmB->id], $user);

        $this->assertCount(1, $result['data']);
        $this->assertSame($tankB->id, $result['data'][0]['id']);
    }

    #[Test]
    public function returns_error_for_foreign_farm(): void
    {
        [$user] = $this->ownerWithFarm();

        $other = User::factory()->create();
        $otherFarm = Farm::factory()->create(['created_by' => $other->id]);
        $otherFarm->users()->attach($other->id, ['role' => 'owner']);

        $result = (new TankListTool)->handle(['farm_id' => $otherFarm->id], $user);

        $this->assertArrayHasKey('error', $result);
    }
}
