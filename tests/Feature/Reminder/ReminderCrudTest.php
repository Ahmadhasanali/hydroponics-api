<?php

namespace Tests\Feature\Reminder;

use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReminderCrudTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setUpFarm(): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        session()->put('selected_farm_id', $farm->id);

        return compact('owner', 'farm');
    }

    public function test_owner_can_create_reminder_targeting_all(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($owner)->post(route('farm.reminders.store', $farm), [
            'title' => 'Tambah AB Mix',
            'body' => 'Tambahkan AB mix ke tank utama',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
            'target_mode' => 'all',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('reminders', [
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'title' => 'Tambah AB Mix',
        ]);

        $reminder = Reminder::where('farm_id', $farm->id)->firstOrFail();

        $this->assertSame(2, $reminder->targets()->count());
        $this->assertSame(1, $reminder->occurrences()->count());
    }

    public function test_manager_cannot_target_owner(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($manager)->post(route('farm.reminders.store', $farm), [
            'title' => 'Reminder ke owner',
            'body' => 'Tidak boleh',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
            'target_mode' => 'specific',
            'target_ids' => [User::class.':'.$owner->id],
        ]);

        $response->assertSessionHasErrors();

        $this->assertDatabaseMissing('reminders', ['title' => 'Reminder ke owner']);
    }

    public function test_creator_can_edit_reminder(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'starts_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($owner)->put(route('farm.reminders.update', [$farm, $reminder]), [
            'title' => 'Judul Baru',
            'body' => 'Body baru',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reminders', ['id' => $reminder->id, 'title' => 'Judul Baru']);
    }

    public function test_non_creator_cannot_edit_reminder(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $other = User::factory()->create();
        $farm->users()->attach($other->id, ['role' => 'manager']);
        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
        ]);

        $response = $this->actingAs($other)->put(route('farm.reminders.update', [$farm, $reminder]), [
            'title' => 'Ditolak',
            'body' => 'Tidak boleh',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertForbidden();
    }

    public function test_target_can_mark_occurrence_done(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);
        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
        ]);
        ReminderTarget::factory()->create([
            'reminder_id' => $reminder->id,
            'targetable_type' => User::class,
            'targetable_id' => $manager->id,
        ]);
        $occurrence = ReminderOccurrence::factory()->create(['reminder_id' => $reminder->id]);

        $response = $this->actingAs($manager)->post(
            route('farm.reminders.occurrence-done', [$farm, $occurrence]),
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('reminder_occurrences', [
            'id' => $occurrence->id,
            'status' => 'done',
            'completed_by_type' => User::class,
            'completed_by_id' => $manager->id,
        ]);
    }
}
