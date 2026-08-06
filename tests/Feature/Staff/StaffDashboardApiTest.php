<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffDashboardApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Farm $farm;

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->farm = Farm::factory()->create();
        $this->staff = Staff::factory()->create(['farm_id' => $this->farm->id]);
    }

    public function test_staff_can_view_dashboard_stats(): void
    {
        Tank::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Tank A', 'created_by' => $this->farm->created_by]);
        Tank::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Tank B', 'created_by' => $this->farm->created_by]);

        Sanctum::actingAs($this->staff, ['staff']);

        $response = $this->getJson('/api/v1/staff/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.farm.id', $this->farm->id)
            ->assertJsonCount(2, 'data.tanks')
            ->assertJsonPath('data.stats.total_tanks', 2);
    }

    public function test_user_account_cannot_access_staff_dashboard(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/staff/dashboard');

        $response->assertStatus(403);
    }

    public function test_inactive_staff_cannot_access_dashboard(): void
    {
        $this->staff->update(['is_active' => false]);

        Sanctum::actingAs($this->staff, ['staff']);

        $response = $this->getJson('/api/v1/staff/dashboard');

        $response->assertStatus(403);
    }
}
