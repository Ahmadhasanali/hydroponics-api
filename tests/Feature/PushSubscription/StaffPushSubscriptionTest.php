<?php

namespace Tests\Feature\PushSubscription;

use App\Models\Farm;
use App\Models\Farm\Staff;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffPushSubscriptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_can_store_token(): void
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $this->actingAs($staff, 'staff')->postJson(route('staff.push-subscriptions.store'), [
            'fcm_token' => 'staff-token-abc',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_type' => Staff::class,
            'subscribable_id' => $staff->id,
            'fcm_token' => 'staff-token-abc',
        ]);
    }

    public function test_staff_can_delete_own_token(): void
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);
        $subscription = PushSubscription::factory()->create([
            'subscribable_type' => Staff::class,
            'subscribable_id' => $staff->id,
        ]);

        $this->actingAs($staff, 'staff')->deleteJson(route('staff.push-subscriptions.destroy'), [
            'fcm_token' => $subscription->fcm_token,
        ])->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $subscription->id]);
    }

    public function test_staff_cannot_claim_token_of_user_with_same_id(): void
    {
        $user = User::factory()->create(['id' => 7]);
        $staff = Staff::factory()->create(['id' => 7, 'farm_id' => Farm::factory()]);
        $subscription = PushSubscription::factory()->forSubscribable($user)->create([
            'fcm_token' => 'shared-id-token',
        ]);

        // Staff shares the same numeric id as the user, but is a different subscribable type.
        $this->actingAs($staff, 'staff')->postJson(route('staff.push-subscriptions.store'), [
            'fcm_token' => $subscription->fcm_token,
        ])->assertConflict()->assertJson(['success' => false]);

        $this->assertDatabaseHas('push_subscriptions', [
            'id' => $subscription->id,
            'subscribable_type' => User::class,
            'subscribable_id' => $user->id,
        ]);

        // The user can still re-register their own token (updates, does not conflict).
        $this->actingAs($user)->postJson(route('push-subscriptions.store'), [
            'fcm_token' => $subscription->fcm_token,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'id' => $subscription->id,
            'subscribable_type' => User::class,
            'subscribable_id' => $user->id,
            'fcm_token' => $subscription->fcm_token,
        ]);
    }
}
