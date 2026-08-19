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

class ReminderListTest extends TestCase
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

    public function test_index_lists_visible_reminders_for_farm(): void
    {
        Reminder::factory()->create([
            'farm_id' => $this->farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $this->owner->id,
            'starts_at' => now()->addDay(),
            'title' => 'Reminder Terlihat',
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reminders?farm_id='.$this->farm->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Reminder Terlihat');
    }

    public function test_index_without_farm_id_returns_all_visible_reminders(): void
    {
        Reminder::factory()->create([
            'farm_id' => $this->farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $this->owner->id,
            'starts_at' => now()->addDay(),
            'title' => 'Reminder Global',
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reminders');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Reminder Global');
    }

    public function test_index_hides_reminder_from_non_creator_non_target(): void
    {
        $outsider = User::factory()->create();
        $this->farm->users()->attach($outsider->id, ['role' => 'manager']);

        Reminder::factory()->create([
            'farm_id' => $this->farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $this->owner->id,
            'starts_at' => now()->addDay(),
            'title' => 'Reminder Rahasia',
        ]);

        $response = $this->actingAs($outsider)->getJson('/api/v1/reminders?farm_id='.$this->farm->id);

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
