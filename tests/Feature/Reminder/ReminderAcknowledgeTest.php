<?php

namespace Tests\Feature\Reminder;

use App\Models\Farm;
use App\Models\Farm\Staff;
use App\Models\Reminder;
use App\Models\Reminder\ReminderNotificationDelivery;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReminderAcknowledgeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeSentUserDelivery(int $minutesAgo = 60): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        $target = User::factory()->create();
        $farm->users()->attach($target->id, ['role' => 'manager']);

        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'recurrence' => ['type' => 'none'],
        ]);

        ReminderTarget::factory()->create([
            'reminder_id' => $reminder->id,
            'targetable_type' => User::class,
            'targetable_id' => $target->id,
        ]);

        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->subHour(),
            'notified_at' => now()->subHour(),
        ]);

        $delivery = ReminderNotificationDelivery::factory()->create([
            'reminder_id' => $reminder->id,
            'occurrence_id' => $occurrence->id,
            'notifiable_type' => User::class,
            'notifiable_id' => $target->id,
            'kind' => 'main',
            'sent_at' => now()->subMinutes($minutesAgo),
            'opened_at' => null,
        ]);

        return compact('owner', 'farm', 'target', 'reminder', 'occurrence', 'delivery');
    }

    public function test_target_user_acknowledges_sent_delivery(): void
    {
        ['target' => $target, 'reminder' => $reminder, 'occurrence' => $occurrence, 'delivery' => $delivery] = $this->makeSentUserDelivery(60);

        $this->actingAs($target)
            ->postJson("/api/v1/reminders/{$reminder->id}/occurrences/{$occurrence->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull($delivery->fresh()->opened_at);
    }

    public function test_ack_before_sent_does_not_fill_opened_at(): void
    {
        ['target' => $target, 'reminder' => $reminder, 'occurrence' => $occurrence] = $this->makeSentUserDelivery(-60);

        $this->actingAs($target)
            ->postJson("/api/v1/reminders/{$reminder->id}/occurrences/{$occurrence->id}/acknowledge")
            ->assertOk();

        $this->assertNull(ReminderNotificationDelivery::query()->first()->fresh()->opened_at);
    }

    public function test_non_target_cannot_acknowledge(): void
    {
        ['reminder' => $reminder, 'occurrence' => $occurrence] = $this->makeSentUserDelivery(60);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->postJson("/api/v1/reminders/{$reminder->id}/occurrences/{$occurrence->id}/acknowledge")
            ->assertForbidden();
    }

    public function test_staff_target_acknowledges(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);
        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => Staff::class,
            'created_by_id' => $staff->id,
            'recurrence' => ['type' => 'none'],
        ]);
        ReminderTarget::factory()->create([
            'reminder_id' => $reminder->id,
            'targetable_type' => Staff::class,
            'targetable_id' => $staff->id,
        ]);
        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'notified_at' => now()->subHour(),
        ]);
        ReminderNotificationDelivery::factory()->create([
            'reminder_id' => $reminder->id,
            'occurrence_id' => $occurrence->id,
            'notifiable_type' => Staff::class,
            'notifiable_id' => $staff->id,
            'kind' => 'main',
            'sent_at' => now()->subMinutes(60),
        ]);

        Sanctum::actingAs($staff, ['staff']);

        $this->postJson("/api/v1/staff/reminders/occurrences/{$occurrence->id}/acknowledge")
            ->assertOk();

        $this->assertNotNull(ReminderNotificationDelivery::query()->first()->fresh()->opened_at);
    }
}
