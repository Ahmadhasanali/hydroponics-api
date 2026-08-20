<?php

namespace Tests\Feature\Reminder;

use App\Http\Controllers\ReminderController;
use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReminderOccurrenceActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $owner;

    private User $manager;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->manager = User::factory()->create();
        $this->farm = Farm::factory()->create(['created_by' => $this->owner->id]);
        $this->farm->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->farm->users()->attach($this->manager->id, ['role' => 'manager']);

        Route::middleware(SubstituteBindings::class)->group(function () {
            Route::prefix('api/v1')->group(function () {
                Route::apiResource('reminders', ReminderController::class);
                Route::post('reminders/{reminder}/occurrences/{occurrence}/done', [ReminderController::class, 'occurrenceDone']);
                Route::post('reminders/{reminder}/occurrences/{occurrence}/skip', [ReminderController::class, 'occurrenceSkip']);
            });
        });
    }

    private function makeReminder(int $createdById): Reminder
    {
        return Reminder::factory()->create([
            'farm_id' => $this->farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $createdById,
        ]);
    }

    public function test_target_can_mark_occurrence_done(): void
    {
        $reminder = $this->makeReminder($this->owner->id);
        $reminder->targets()->create([
            'targetable_type' => User::class,
            'targetable_id' => $this->manager->id,
        ]);
        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/v1/reminders/{$reminder->id}/occurrences/{$occurrence->id}/done");

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('reminder_occurrences', [
            'id' => $occurrence->id,
            'status' => 'done',
            'completed_by_type' => User::class,
            'completed_by_id' => $this->manager->id,
        ]);
    }

    public function test_creator_can_skip_occurrence(): void
    {
        $reminder = $this->makeReminder($this->owner->id);
        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/reminders/{$reminder->id}/occurrences/{$occurrence->id}/skip");

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('reminder_occurrences', [
            'id' => $occurrence->id,
            'status' => 'skipped',
        ]);
    }

    public function test_non_creator_non_target_cannot_mark_occurrence(): void
    {
        $outsider = User::factory()->create();
        $this->farm->users()->attach($outsider->id, ['role' => 'manager']);
        $reminder = $this->makeReminder($this->owner->id);
        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($outsider)
            ->postJson("/api/v1/reminders/{$reminder->id}/occurrences/{$occurrence->id}/done");

        $response->assertForbidden();
    }

    public function test_occurrence_of_other_reminder_rejected(): void
    {
        $reminder = $this->makeReminder($this->owner->id);
        $other = $this->makeReminder($this->owner->id);
        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $other->id,
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/reminders/{$reminder->id}/occurrences/{$occurrence->id}/done");

        $response->assertNotFound();
    }
}
