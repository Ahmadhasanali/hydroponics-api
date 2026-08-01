<?php

namespace Tests\Feature\PushSubscription;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PushSubscriptionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_store_token(): void
    {
        $this->postJson(route('push-subscriptions.store'), [
            'fcm_token' => 'token-abc',
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_store_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('push-subscriptions.store'), [
            'fcm_token' => 'token-abc',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'fcm_token' => 'token-abc',
            'platform' => 'android',
        ]);
    }

    public function test_fcm_token_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('push-subscriptions.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fcm_token');
    }

    public function test_authenticated_user_can_delete_own_token(): void
    {
        $user = User::factory()->create();
        $subscription = PushSubscription::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->deleteJson(route('push-subscriptions.destroy'), [
            'fcm_token' => $subscription->fcm_token,
        ])->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $subscription->id]);
    }

    public function test_user_cannot_delete_another_users_token(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $subscription = PushSubscription::factory()->create(['user_id' => $other->id]);

        $this->actingAs($owner)->deleteJson(route('push-subscriptions.destroy'), [
            'fcm_token' => $subscription->fcm_token,
        ])->assertOk();

        $this->assertDatabaseHas('push_subscriptions', ['id' => $subscription->id]);
    }
}
