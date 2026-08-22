<?php

namespace Tests\Feature\Reminder;

use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReminderHistoryFilterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeOwnerFarm(): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);

        return compact('owner', 'farm');
    }

    public function test_sent_one_time_appears_in_history_only(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->makeOwnerFarm();

        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'recurrence' => ['type' => 'none'],
        ]);
        ReminderTarget::factory()->create([
            'reminder_id' => $reminder->id,
            'targetable_type' => User::class,
            'targetable_id' => $owner->id,
        ]);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->subHour(),
            'notified_at' => now()->subHour(),
        ]);

        $this->actingAs($owner)->getJson('/api/v1/reminders?history=1')
            ->assertOk()->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $reminder->id]);

        $this->actingAs($owner)->getJson('/api/v1/reminders?upcoming=1')
            ->assertOk()->assertJsonMissing(['id' => $reminder->id]);
    }

    public function test_recurring_active_stays_in_upcoming_not_history(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->makeOwnerFarm();

        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'recurrence' => ['type' => 'interval', 'every_days' => 1],
        ]);
        ReminderTarget::factory()->create([
            'reminder_id' => $reminder->id,
            'targetable_type' => User::class,
            'targetable_id' => $owner->id,
        ]);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDay(),
            'notified_at' => null,
        ]);

        $this->actingAs($owner)->getJson('/api/v1/reminders?upcoming=1')
            ->assertOk()->assertJsonFragment(['id' => $reminder->id]);

        $this->actingAs($owner)->getJson('/api/v1/reminders?history=1')
            ->assertOk()->assertJsonMissing(['id' => $reminder->id]);
    }

    public function test_done_without_notification_is_history(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->makeOwnerFarm();

        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'recurrence' => ['type' => 'none'],
        ]);
        ReminderTarget::factory()->create([
            'reminder_id' => $reminder->id,
            'targetable_type' => User::class,
            'targetable_id' => $owner->id,
        ]);
        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->subHour(),
            'notified_at' => null,
        ]);
        $occurrence->markDone(User::class, $owner->id);

        $this->actingAs($owner)->getJson('/api/v1/reminders?history=1')
            ->assertOk()->assertJsonFragment(['id' => $reminder->id]);
    }
}
