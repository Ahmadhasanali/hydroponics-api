<?php

namespace Tests\Feature\PushSubscription;

use App\Models\Farm;
use App\Models\Farm\Staff;
use App\Models\PushSubscription;
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
}
