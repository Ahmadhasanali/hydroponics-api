<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\ReminderController;
use App\Models\Farm;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReminderGlobalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(SubstituteBindings::class)->group(function () {
            Route::get('api/v1/reminders', [ReminderController::class, 'index']);
            Route::post('api/v1/reminders', [ReminderController::class, 'store']);
        });
    }

    #[Test]
    public function index_without_farm_id_returns_all_visible_reminders(): void
    {
        $user = User::factory()->create();
        $farmA = Farm::factory()->create(['created_by' => $user->id]);
        $farmB = Farm::factory()->create(['created_by' => $user->id]);
        $user->farms()->attach($farmA->id, ['role' => 'owner']);
        $user->farms()->attach($farmB->id, ['role' => 'owner']);

        Reminder::factory()->create([
            'farm_id' => $farmA->id,
            'created_by_type' => User::class,
            'created_by_id' => $user->id,
            'title' => 'Reminder A',
            'starts_at' => now()->addDay()->setTime(8, 0),
        ]);
        Reminder::factory()->create([
            'farm_id' => $farmB->id,
            'created_by_type' => User::class,
            'created_by_id' => $user->id,
            'title' => 'Reminder B',
            'starts_at' => now()->addDay()->setTime(7, 0),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/reminders');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.farm_id', $farmB->id)
            ->assertJsonPath('data.0.farm.name', $farmB->name)
            ->assertJsonPath('data.1.farm_id', $farmA->id);
    }
}
