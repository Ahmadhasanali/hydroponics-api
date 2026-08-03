<?php

namespace Tests\Unit\Models;

use App\Enums\ReminderStatus;
use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReminderModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reminder_relations_and_casts(): void
    {
        $farm = Farm::factory()->create();
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $user->id,
            'recurrence' => ['type' => 'weekly', 'days_of_week' => ['mon']],
        ]);

        $this->assertTrue($reminder->farm->is($farm));
        $this->assertTrue($reminder->creator->is($user));
        $this->assertSame(['type' => 'weekly', 'days_of_week' => ['mon']], $reminder->recurrence);
        $this->assertTrue($reminder->isRecurring());
        $this->assertSame('weekly', $reminder->recurrenceType()->value);
    }

    public function test_occurrence_default_status_pending(): void
    {
        $reminder = Reminder::factory()->create();
        $occurrence = ReminderOccurrence::factory()->create(['reminder_id' => $reminder->id]);

        $this->assertSame(ReminderStatus::Pending, $occurrence->status);
    }

    public function test_occurrence_mark_done_and_skipped(): void
    {
        $reminder = Reminder::factory()->create();
        $user = User::factory()->create();
        $occurrence = ReminderOccurrence::factory()->create(['reminder_id' => $reminder->id]);

        $occurrence->markDone(User::class, $user->id);

        $this->assertSame(ReminderStatus::Done, $occurrence->status);
        $this->assertTrue($occurrence->completer->is($user));
        $this->assertNotNull($occurrence->completed_at);
        $this->assertSame(User::class, $occurrence->fresh()->completed_by_type);
        $this->assertSame($user->id, $occurrence->fresh()->completed_by_id);
        $this->assertNotNull($occurrence->fresh()->completed_at);

        $occurrence->markSkipped();

        $this->assertSame(ReminderStatus::Skipped, $occurrence->status);
    }

    public function test_target_relations(): void
    {
        $reminder = Reminder::factory()->create();
        $user = User::factory()->create();
        $target = ReminderTarget::factory()->create([
            'reminder_id' => $reminder->id,
            'targetable_type' => User::class,
            'targetable_id' => $user->id,
        ]);

        $this->assertTrue($target->reminder->is($reminder));
        $this->assertTrue($target->targetable->is($user));
    }
}
