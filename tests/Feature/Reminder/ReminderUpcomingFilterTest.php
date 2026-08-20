<?php

namespace Tests\Feature\Reminder;

use App\Enums\ReminderStatus;
use App\Http\Controllers\ReminderController;
use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReminderUpcomingFilterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $owner;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->farm = Farm::factory()->create(['created_by' => $this->owner->id]);
        $this->farm->users()->attach($this->owner->id, ['role' => 'owner']);

        Route::middleware(SubstituteBindings::class)->group(function () {
            Route::prefix('api/v1')->group(function () {
                Route::apiResource('reminders', ReminderController::class);
            });
        });
    }

    private function makeReminder(array $attributes = []): Reminder
    {
        return Reminder::factory()->create(array_merge([
            'farm_id' => $this->farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $this->owner->id,
        ], $attributes));
    }

    public function test_upcoming_includes_first_cycle_reminder_before_notification(): void
    {
        $reminder = $this->makeReminder(['starts_at' => now()->addDays(10), 'title' => 'Siklus Pertama']);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDays(10),
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reminders?upcoming=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Siklus Pertama');
    }

    public function test_upcoming_hides_non_recurring_reminder_after_notified(): void
    {
        $reminder = $this->makeReminder(['starts_at' => now()->addDay(), 'title' => 'Sudah Kirim']);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDay(),
            'notified_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reminders?upcoming=1');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_upcoming_includes_recurring_reminder_when_next_occurrence_within_window(): void
    {
        $reminder = $this->makeReminder(['recurrence' => ['type' => 'weekly', 'days_of_week' => ['mon']]]);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->subWeek(),
            'notified_at' => now()->subWeek(),
        ]);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reminders?upcoming=1');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_upcoming_hides_recurring_reminder_when_next_occurrence_beyond_window(): void
    {
        $reminder = $this->makeReminder(['recurrence' => ['type' => 'weekly', 'days_of_week' => ['mon']]]);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->subWeek(),
            'notified_at' => now()->subWeek(),
        ]);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDays(10),
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reminders?upcoming=1');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_upcoming_hides_inactive_reminder(): void
    {
        $this->makeReminder(['starts_at' => now()->addDay(), 'is_active' => false]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reminders?upcoming=1');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_upcoming_hides_reminder_with_only_done_or_skipped_occurrences(): void
    {
        $reminder = $this->makeReminder(['starts_at' => now()->addDay()]);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDay(),
            'status' => ReminderStatus::Done,
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reminders?upcoming=1');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_index_without_upcoming_param_still_returns_all_visible(): void
    {
        $reminder = $this->makeReminder(['starts_at' => now()->addDay()]);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDay(),
            'notified_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reminders');

        $response->assertOk()->assertJsonCount(1, 'data');
    }
}
