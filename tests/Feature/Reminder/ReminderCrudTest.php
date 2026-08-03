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

    public function test_reminder_from_other_farm_is_hidden_from_index_and_occurrence_done_forbidden(): void
    {
        // Farm A: reminder dibuat oleh owner A
        $ownerA = User::factory()->create();
        $farmA = Farm::factory()->create(['created_by' => $ownerA->id]);
        $farmA->users()->attach($ownerA->id, ['role' => 'owner']);

        $reminderA = Reminder::factory()->create([
            'farm_id' => $farmA->id,
            'created_by_type' => User::class,
            'created_by_id' => $ownerA->id,
            'title' => 'Reminder Khusus Farm A',
        ]);
        $occurrenceA = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminderA->id,
            'scheduled_at' => now()->subMinute(),
        ]);

        // Farm B: owner A juga anggota (owner)
        $farmB = Farm::factory()->create(['created_by' => $ownerA->id]);
        $farmB->users()->attach($ownerA->id, ['role' => 'owner']);
        session()->put('selected_farm_id', $farmB->id);

        // Index farm B tidak boleh menampilkan reminder dari farm A
        $response = $this->actingAs($ownerA)->get(route('farm.reminders.index', $farmB));

        $response->assertOk();
        $response->assertDontSee('Reminder Khusus Farm A');

        // occurrenceDone via route farm B harus 403
        $response = $this->actingAs($ownerA)->post(
            route('farm.reminders.occurrence-done', [$farmB, $occurrenceA]),
        );

        $response->assertForbidden();
    }

    public function test_malformed_target_ids_rejected_without_creating_reminder(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($owner)->post(route('farm.reminders.store', $farm), [
            'title' => 'Target Rusak',
            'body' => 'Tidak boleh tersimpan',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
            'target_mode' => 'specific',
            'target_ids' => ['garbage', User::class.':abc'],
        ]);

        $response->assertSessionHasErrors('target_ids.*');

        $this->assertDatabaseMissing('reminders', ['title' => 'Target Rusak']);
    }

    public function test_valid_specific_target_ids_creates_reminder_for_same_farm_manager(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($owner)->post(route('farm.reminders.store', $farm), [
            'title' => 'Reminder ke Manager',
            'body' => 'Jadwal rutin',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
            'target_mode' => 'specific',
            'target_ids' => [User::class.':'.$manager->id],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('reminders', [
            'farm_id' => $farm->id,
            'title' => 'Reminder ke Manager',
        ]);

        $reminder = Reminder::where('farm_id', $farm->id)
            ->where('title', 'Reminder ke Manager')
            ->firstOrFail();

        $this->assertDatabaseHas('reminder_targets', [
            'reminder_id' => $reminder->id,
            'targetable_type' => User::class,
            'targetable_id' => $manager->id,
        ]);
    }
}
