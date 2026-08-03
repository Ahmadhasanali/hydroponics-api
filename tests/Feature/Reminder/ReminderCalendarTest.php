<?php

namespace Tests\Feature\Reminder;

use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReminderCalendarTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_calendar_shows_visible_occurrences_for_month(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);

        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'starts_at' => now()->startOfMonth()->addDays(5)->setTime(8, 0),
        ]);

        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => $reminder->starts_at,
        ]);

        $response = $this->actingAs($owner)->get(route('farm.reminders.calendar', $farm));

        $response->assertOk();
        $response->assertSee($reminder->title);
    }

    public function test_calendar_hides_reminder_from_non_creator_non_target(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        $outsider = User::factory()->create();
        $farm->users()->attach($outsider->id, ['role' => 'manager']);

        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'starts_at' => now()->startOfMonth()->addDays(5)->setTime(8, 0),
            'title' => 'Reminder Rahasia',
        ]);

        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => $reminder->starts_at,
        ]);

        $response = $this->actingAs($outsider)->get(route('farm.reminders.calendar', $farm));

        $response->assertOk();
        $response->assertDontSee('Reminder Rahasia');
    }
}
