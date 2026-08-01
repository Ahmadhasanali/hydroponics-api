<?php

namespace Tests\Feature\Commands;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class NotifyDailyMonitoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sends_reminder_to_users_with_subscriptions_only(): void
    {
        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')->once()->with(
            Mockery::type(User::class),
            'Waktunya Monitoring',
            Mockery::type('string'),
            Mockery::type('string'),
        );
        $this->app->instance(PushNotificationService::class, $push);

        $withDevice = User::factory()->create();
        PushSubscription::factory()->create(['user_id' => $withDevice->id]);
        User::factory()->create();

        $this->artisan('notify:daily-monitoring')->assertExitCode(0);
    }

    public function test_does_not_send_twice_on_same_day(): void
    {
        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')->once();
        $this->app->instance(PushNotificationService::class, $push);

        $user = User::factory()->create();
        PushSubscription::factory()->create(['user_id' => $user->id]);

        $this->artisan('notify:daily-monitoring')->assertExitCode(0);
        $this->artisan('notify:daily-monitoring')->assertExitCode(0);
    }
}
