<?php

namespace Tests\Unit\Services;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Mockery;
use Tests\TestCase;

class PushNotificationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_skips_when_user_has_no_subscriptions(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldNotReceive('send');

        $service = new PushNotificationService($messaging);
        $user = User::factory()->create();

        $service->sendToUser($user, 'Judul', 'Isi');
        $this->addToAssertionCount(1);
    }

    public function test_sends_to_each_subscription(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->twice();

        $service = new PushNotificationService($messaging);
        $user = User::factory()->create();
        PushSubscription::factory()->count(2)->forSubscribable($user)->create();

        $service->sendToUser($user, 'Judul', 'Isi', '/dashboard');
    }

    public function test_deletes_unregistered_token(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->andThrow(new NotFound('Token tidak terdaftar'));

        $service = new PushNotificationService($messaging);
        $user = User::factory()->create();
        $subscription = PushSubscription::factory()->forSubscribable($user)->create();

        $service->sendToUser($user, 'Judul', 'Isi');

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $subscription->id]);
    }

    public function test_logs_other_errors_and_keeps_token(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->andThrow(new InvalidMessage('Pesan tidak valid'));

        $service = new PushNotificationService($messaging);
        $user = User::factory()->create();
        $subscription = PushSubscription::factory()->forSubscribable($user)->create();

        $service->sendToUser($user, 'Judul', 'Isi');

        $this->assertDatabaseHas('push_subscriptions', ['id' => $subscription->id]);
    }
}
