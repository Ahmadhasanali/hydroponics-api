<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\Staff;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffReminderApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Farm $farm;

    private Staff $staff;

    private Staff $colleague;

    protected function setUp(): void
    {
        parent::setUp();
        $this->farm = Farm::factory()->create();
        $this->staff = Staff::factory()->create(['farm_id' => $this->farm->id]);
        $this->colleague = Staff::factory()->create(['farm_id' => $this->farm->id]);
        Sanctum::actingAs($this->staff, ['staff']);
    }

    public function test_staff_can_list_visible_reminders(): void
    {
        $created = Reminder::factory()->create([
            'farm_id' => $this->farm->id,
            'created_by_type' => Staff::class,
            'created_by_id' => $this->staff->id,
            'starts_at' => now()->addDay(),
        ]);

        $targeted = Reminder::factory()->create([
            'farm_id' => $this->farm->id,
            'created_by_type' => Staff::class,
            'created_by_id' => $this->colleague->id,
            'starts_at' => now()->addDay(),
        ]);
        ReminderTarget::factory()->create([
            'reminder_id' => $targeted->id,
            'targetable_type' => Staff::class,
            'targetable_id' => $this->staff->id,
        ]);

        $this->getJson('/api/v1/staff/reminders')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_staff_can_create_reminder_targeting_colleague(): void
    {
        $response = $this->postJson('/api/v1/staff/reminders', [
            'title' => 'Siram pagi',
            'body' => 'Siram tank A',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'target_mode' => 'specific',
            'target_ids' => [Staff::class.':'.$this->colleague->id],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reminder.created_by_id', $this->staff->id);

        $this->assertDatabaseHas('reminders', ['title' => 'Siram pagi', 'created_by_id' => $this->staff->id]);
    }

    public function test_staff_can_delete_own_reminder(): void
    {
        $reminder = Reminder::factory()->create([
            'farm_id' => $this->farm->id,
            'created_by_type' => Staff::class,
            'created_by_id' => $this->staff->id,
            'starts_at' => now()->addDay(),
        ]);

        $this->deleteJson("/api/v1/staff/reminders/{$reminder->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('reminders', ['id' => $reminder->id]);
    }

    public function test_staff_cannot_delete_reminder_created_by_colleague(): void
    {
        $reminder = Reminder::factory()->create([
            'farm_id' => $this->farm->id,
            'created_by_type' => Staff::class,
            'created_by_id' => $this->colleague->id,
            'starts_at' => now()->addDay(),
        ]);

        $this->deleteJson("/api/v1/staff/reminders/{$reminder->id}")
            ->assertStatus(403);
    }

    public function test_staff_can_view_calendar_for_month(): void
    {
        Reminder::factory()->create([
            'farm_id' => $this->farm->id,
            'created_by_type' => Staff::class,
            'created_by_id' => $this->staff->id,
            'starts_at' => now()->startOfMonth()->addDay()->setTime(8, 0),
        ]);

        $month = now()->format('Y-m');

        $this->getJson('/api/v1/staff/reminders/calendar?month='.$month)
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_staff_can_mark_occurrence_done(): void
    {
        $reminder = Reminder::factory()->create([
            'farm_id' => $this->farm->id,
            'created_by_type' => Staff::class,
            'created_by_id' => $this->staff->id,
            'starts_at' => now()->addDay(),
        ]);

        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDay(),
        ]);

        $this->postJson("/api/v1/staff/reminders/occurrences/{$occurrence->id}/done")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('reminder_occurrences', [
            'id' => $occurrence->id,
            'completed_by_type' => Staff::class,
            'completed_by_id' => $this->staff->id,
        ]);
    }

    public function test_staff_can_skip_occurrence(): void
    {
        $reminder = Reminder::factory()->create([
            'farm_id' => $this->farm->id,
            'created_by_type' => Staff::class,
            'created_by_id' => $this->staff->id,
            'starts_at' => now()->addDay(),
        ]);

        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDay(),
        ]);

        $this->postJson("/api/v1/staff/reminders/occurrences/{$occurrence->id}/skip")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('reminder_occurrences', [
            'id' => $occurrence->id,
            'status' => 'skipped',
        ]);
    }
}
