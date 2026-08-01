<?php

namespace Tests\Unit\ChatTools;

use App\ChatTools\FarmListTool;
use App\Models\Farm;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FarmListToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function returns_only_farms_owned_by_the_user(): void
    {
        $user = User::factory()->create();
        $ownFarm = Farm::factory()->create(['created_by' => $user->id]);
        $ownFarm->users()->attach($user->id, ['role' => 'owner']);
        Tank::factory()->count(2)->create(['farm_id' => $ownFarm->id, 'created_by' => $user->id]);

        $otherUser = User::factory()->create();
        $foreignFarm = Farm::factory()->create(['created_by' => $otherUser->id]);
        $foreignFarm->users()->attach($otherUser->id, ['role' => 'owner']);

        $result = (new FarmListTool)->handle([], $user);

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertSame($ownFarm->id, $result['data'][0]['id']);
        $this->assertSame($ownFarm->name, $result['data'][0]['name']);
        $this->assertSame(2, $result['data'][0]['tank_count']);
    }
}
