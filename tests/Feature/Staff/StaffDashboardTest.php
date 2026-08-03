<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\Staff;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_can_view_dashboard(): void
    {
        $farm = Farm::factory()->create(['name' => 'Kebun A']);
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $response = $this->actingAs($staff, 'staff')->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('Kebun A');
    }

    public function test_guest_cannot_access_staff_dashboard(): void
    {
        $response = $this->get(route('staff.dashboard'));

        $response->assertRedirect(route('staff.login'));
    }
}
