<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\Staff;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffReminderTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setUpStaff(): array
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);
        $otherStaff = Staff::factory()->create(['farm_id' => $farm->id]);

        return compact('farm', 'staff', 'otherStaff');
    }

    public function test_staff_can_create_reminder_targeting_self(): void
    {
        ['farm' => $farm, 'staff' => $staff] = $this->setUpStaff();

        $response = $this->actingAs($staff, 'staff')->post(route('staff.reminders.store'), [
            'title' => 'Cek pH',
            'body' => 'Cek pH tank 1',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
            'target_mode' => 'self',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reminders', [
            'farm_id' => $farm->id,
            'created_by_type' => Staff::class,
            'created_by_id' => $staff->id,
            'title' => 'Cek pH',
        ]);
    }

    public function test_staff_can_target_other_staff_in_same_farm(): void
    {
        ['farm' => $farm, 'staff' => $staff, 'otherStaff' => $otherStaff] = $this->setUpStaff();

        $response = $this->actingAs($staff, 'staff')->post(route('staff.reminders.store'), [
            'title' => 'Bantu cek',
            'body' => 'Tolong cek tank',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
            'target_mode' => 'specific',
            'target_ids' => [Staff::class.':'.$otherStaff->id],
        ]);

        $response->assertRedirect();

        $reminder = Reminder::where('title', 'Bantu cek')->firstOrFail();

        $this->assertDatabaseHas('reminder_targets', [
            'reminder_id' => $reminder->id,
            'targetable_type' => Staff::class,
            'targetable_id' => $otherStaff->id,
        ]);
    }

    public function test_staff_cannot_target_manager_user(): void
    {
        ['farm' => $farm, 'staff' => $staff] = $this->setUpStaff();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($staff, 'staff')->post(route('staff.reminders.store'), [
            'title' => 'Ke manager',
            'body' => 'Tidak boleh',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
            'target_mode' => 'specific',
            'target_ids' => [User::class.':'.$manager->id],
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('reminders', ['title' => 'Ke manager']);
    }

    public function test_staff_can_mark_own_reminder_occurrence_done(): void
    {
        ['farm' => $farm, 'staff' => $staff] = $this->setUpStaff();
        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => Staff::class,
            'created_by_id' => $staff->id,
        ]);
        $occurrence = ReminderOccurrence::factory()->create(['reminder_id' => $reminder->id]);

        $response = $this->actingAs($staff, 'staff')->post(
            route('staff.reminders.occurrence-done', $occurrence),
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('reminder_occurrences', [
            'id' => $occurrence->id,
            'status' => 'done',
            'completed_by_type' => Staff::class,
            'completed_by_id' => $staff->id,
        ]);
    }

    public function test_staff_cannot_see_reminder_of_other_farm(): void
    {
        ['farm' => $farm, 'staff' => $staff] = $this->setUpStaff();
        $otherFarm = Farm::factory()->create();
        $otherReminder = Reminder::factory()->create([
            'farm_id' => $otherFarm->id,
            'created_by_type' => Staff::class,
            'created_by_id' => Staff::factory()->create(['farm_id' => $otherFarm->id])->id,
        ]);

        $response = $this->actingAs($staff, 'staff')->get(route('staff.reminders.index'));

        $response->assertOk();
        $response->assertDontSee($otherReminder->title);
    }
}
