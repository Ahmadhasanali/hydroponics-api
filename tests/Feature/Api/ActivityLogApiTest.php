<?php

namespace Tests\Feature\Api;

use App\Models\Farm;
use App\Models\Farm\ActivityLog;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityLogApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function updating_a_tank_records_before_and_after_state(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $user->farms()->attach($farm->id, ['role' => 'owner']);
        $tank = Tank::factory()->create([
            'farm_id' => $farm->id,
            'created_by' => $user->id,
            'name' => 'Tank Lama',
            'capacity_liter' => 100,
        ]);

        $this->actingAs($user);

        $tank->update(['name' => 'Tank Baru']);

        $log = ActivityLog::where('entity_type', 'tank')
            ->where('entity_id', $tank->id)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('Tank Lama', $log->before_state['name']);
        $this->assertSame('Tank Baru', $log->after_state['name']);
    }
}
