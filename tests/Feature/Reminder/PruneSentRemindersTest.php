<?php

namespace Tests\Feature\Reminder;

use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Reminder\ReminderNotificationDelivery;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PruneSentRemindersTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeSentOneTime(int $daysAgo, string $recurrenceType = 'none'): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);

        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'recurrence' => ['type' => $recurrenceType],
        ]);

        $target = ReminderTarget::factory()->create([
            'reminder_id' => $reminder->id,
            'targetable_type' => User::class,
            'targetable_id' => $owner->id,
        ]);

        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->subDays($daysAgo),
            'notified_at' => now()->subDays($daysAgo),
        ]);

        $delivery = ReminderNotificationDelivery::factory()->create([
            'reminder_id' => $reminder->id,
            'occurrence_id' => $occurrence->id,
            'notifiable_type' => User::class,
            'notifiable_id' => $owner->id,
            'kind' => 'main',
            'sent_at' => now()->subDays($daysAgo),
        ]);

        return compact('reminder', 'occurrence', 'target', 'delivery');
    }

    public function test_old_sent_one_time_is_pruned_with_children(): void
    {
        ['reminder' => $reminder] = $this->makeSentOneTime(91);

        $this->artisan('reminders:prune-sent')->assertExitCode(0);

        $this->assertDatabaseMissing('reminders', ['id' => $reminder->id]);
        $this->assertDatabaseMissing('reminder_occurrences', ['reminder_id' => $reminder->id]);
        $this->assertDatabaseMissing('reminder_targets', ['reminder_id' => $reminder->id]);
        $this->assertDatabaseMissing('reminder_notification_deliveries', ['reminder_id' => $reminder->id]);
    }

    public function test_recent_one_time_survives(): void
    {
        ['reminder' => $reminder] = $this->makeSentOneTime(89);

        $this->artisan('reminders:prune-sent')->assertExitCode(0);

        $this->assertDatabaseHas('reminders', ['id' => $reminder->id]);
    }

    public function test_old_recurring_survives(): void
    {
        ['reminder' => $reminder] = $this->makeSentOneTime(91, 'interval');

        $this->artisan('reminders:prune-sent')->assertExitCode(0);

        $this->assertDatabaseHas('reminders', ['id' => $reminder->id]);
    }

    public function test_soft_deleted_eligible_is_force_deleted(): void
    {
        ['reminder' => $reminder] = $this->makeSentOneTime(91);
        $reminder->delete();

        $this->artisan('reminders:prune-sent')->assertExitCode(0);

        $this->assertDatabaseMissing('reminders', ['id' => $reminder->id]);
    }
}
