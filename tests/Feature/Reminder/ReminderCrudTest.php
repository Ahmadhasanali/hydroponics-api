<?php

namespace Tests\Feature\Reminder;

use App\Http\Controllers\ReminderController;
use App\Models\Farm;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReminderCrudTest extends TestCase
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
            });
        });
    }

    public function test_owner_can_create_reminder_targeting_all(): void
    {
        $response = $this->actingAs($this->owner)->postJson('/api/v1/reminders', [
            'farm_id' => $this->farm->id,
            'title' => 'Tambah AB Mix',
            'body' => 'Tambahkan AB mix ke tank utama',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
            'target_mode' => 'all',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reminder.farm_id', $this->farm->id)
            ->assertJsonPath('data.reminder.title', 'Tambah AB Mix');

        $reminder = Reminder::where('farm_id', $this->farm->id)->firstOrFail();

        $this->assertSame(2, $reminder->targets()->count());
        $this->assertSame(1, $reminder->occurrences()->count());
    }

    public function test_manager_cannot_target_owner(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/api/v1/reminders', [
            'farm_id' => $this->farm->id,
            'title' => 'Reminder ke owner',
            'body' => 'Tidak boleh',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
            'target_mode' => 'specific',
            'target_ids' => [User::class.':'.$this->owner->id],
        ]);

        $response->assertUnprocessable();

        $this->assertDatabaseMissing('reminders', ['title' => 'Reminder ke owner']);
    }

    public function test_creator_can_edit_reminder(): void
    {
        $reminder = Reminder::factory()->create([
            'farm_id' => $this->farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $this->owner->id,
            'starts_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->owner)->putJson("/api/v1/reminders/{$reminder->id}", [
            'title' => 'Judul Baru',
            'body' => 'Body baru',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.reminder.title', 'Judul Baru');

        $this->assertDatabaseHas('reminders', ['id' => $reminder->id, 'title' => 'Judul Baru']);
    }

    public function test_non_creator_cannot_edit_reminder(): void
    {
        $reminder = Reminder::factory()->create([
            'farm_id' => $this->farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->manager)->putJson("/api/v1/reminders/{$reminder->id}", [
            'title' => 'Ditolak',
            'body' => 'Tidak boleh',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertForbidden();
    }

    public function test_reminder_from_other_farm_is_hidden_from_index(): void
    {
        $farmB = Farm::factory()->create(['created_by' => $this->owner->id]);
        $farmB->users()->attach($this->owner->id, ['role' => 'owner']);

        Reminder::factory()->create([
            'farm_id' => $farmB->id,
            'created_by_type' => User::class,
            'created_by_id' => $this->owner->id,
            'title' => 'Reminder Khusus Farm B',
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reminders?farm_id='.$this->farm->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_malformed_target_ids_rejected_without_creating_reminder(): void
    {
        $response = $this->actingAs($this->owner)->postJson('/api/v1/reminders', [
            'farm_id' => $this->farm->id,
            'title' => 'Target Rusak',
            'body' => 'Tidak boleh tersimpan',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
            'target_mode' => 'specific',
            'target_ids' => ['garbage', User::class.':abc'],
        ]);

        $response->assertUnprocessable();

        $this->assertDatabaseMissing('reminders', ['title' => 'Target Rusak']);
    }

    public function test_valid_specific_target_ids_creates_reminder_for_same_farm_manager(): void
    {
        $response = $this->actingAs($this->owner)->postJson('/api/v1/reminders', [
            'farm_id' => $this->farm->id,
            'title' => 'Reminder ke Manager',
            'body' => 'Jadwal rutin',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
            'target_mode' => 'specific',
            'target_ids' => [User::class.':'.$this->manager->id],
        ]);

        $response->assertCreated();

        $reminder = Reminder::where('farm_id', $this->farm->id)
            ->where('title', 'Reminder ke Manager')
            ->firstOrFail();

        $this->assertDatabaseHas('reminder_targets', [
            'reminder_id' => $reminder->id,
            'targetable_type' => User::class,
            'targetable_id' => $this->manager->id,
        ]);
    }
}
