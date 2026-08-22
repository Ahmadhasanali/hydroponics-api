<?php

namespace Tests\Feature\Reminder;

use App\Models\Reminder;
use App\Models\Reminder\ReminderNotificationDelivery;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReminderNotificationDeliveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_delivery_persists_with_relations_and_casts(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create();
        $occurrence = ReminderOccurrence::factory()->create(['reminder_id' => $reminder->id]);

        $delivery = ReminderNotificationDelivery::factory()->create([
            'reminder_id' => $reminder->id,
            'occurrence_id' => $occurrence->id,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'kind' => 'main',
            'sent_at' => now()->subMinutes(5),
        ]);

        $this->assertTrue($delivery->reminder->is($reminder));
        $this->assertTrue($delivery->occurrence->is($occurrence));
        $this->assertTrue($delivery->notifiable->is($user));
        $this->assertNull($delivery->opened_at);
        $this->assertSame('main', $delivery->kind);
        $this->assertInstanceOf(Carbon::class, $delivery->sent_at);
    }
}
