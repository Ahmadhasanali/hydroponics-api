<?php

namespace Tests\Feature\FarmActivity;

use App\Jobs\NotifyFarmActivity;
use App\Models\Farm;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Tank;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class NotifyFarmActivityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setupFarm(array $roles): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);

        foreach ($roles as $name => $role) {
            $user = User::factory()->create();
            $farm->users()->attach($user->id, ['role' => $role]);
            $users[$name] = $user;
        }

        $users['owner'] = $owner;
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $owner->id]);
        session()->put('selected_farm_id', $farm->id);

        return compact('farm', 'tank', 'users');
    }

    public function test_activity_notifies_other_farm_members_except_actor(): void
    {
        $received = [];
        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')->andReturnUsing(function (User $user) use (&$received): void {
            $received[] = $user->id;
        });
        $this->app->instance(PushNotificationService::class, $push);

        ['tank' => $tank, 'users' => $users] = $this->setupFarm([
            'actor' => 'operator',
            'member' => 'operator',
        ]);

        DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'user_id' => $users['actor']->id,
        ]);

        $this->assertEqualsCanonicalizing([$users['owner']->id, $users['member']->id], $received);
    }

    public function test_job_dispatched_when_record_created(): void
    {
        Queue::fake();

        ['tank' => $tank, 'users' => $users] = $this->setupFarm(['actor' => 'operator']);

        DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'user_id' => $users['actor']->id,
        ]);

        Queue::assertPushed(NotifyFarmActivity::class);
    }
}
