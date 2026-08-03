<?php

namespace Tests\Feature\StaffAuth;

use App\Models\Farm;
use App\Models\Farm\Staff;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffAuthTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_can_login(): void
    {
        $farm = Farm::factory()->create(['name' => 'Kebun A']);
        $staff = Staff::factory()->create(['farm_id' => $farm->id, 'username' => 'anton', 'password' => 'password']);

        $response = $this->post(route('staff.login.attempt'), [
            'farm_name' => 'Kebun A',
            'username' => 'anton',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('staff.dashboard'));
        $this->assertAuthenticatedAs($staff, 'staff');
    }

    public function test_staff_login_with_wrong_password_fails(): void
    {
        $farm = Farm::factory()->create(['name' => 'Kebun A']);
        Staff::factory()->create(['farm_id' => $farm->id, 'username' => 'anton', 'password' => 'password']);

        $response = $this->post(route('staff.login.attempt'), [
            'farm_name' => 'Kebun A',
            'username' => 'anton',
            'password' => 'salah',
        ]);

        $response->assertSessionHasErrors('farm_name');
        $this->assertGuest('staff');
    }

    public function test_staff_login_with_unknown_farm_fails(): void
    {
        $farm = Farm::factory()->create(['name' => 'Kebun A']);
        Staff::factory()->create(['farm_id' => $farm->id, 'username' => 'anton', 'password' => 'password']);

        $response = $this->post(route('staff.login.attempt'), [
            'farm_name' => 'Kebun Tidak Ada',
            'username' => 'anton',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('farm_name');
    }

    public function test_inactive_staff_cannot_login(): void
    {
        $farm = Farm::factory()->create(['name' => 'Kebun A']);
        Staff::factory()->create(['farm_id' => $farm->id, 'username' => 'anton', 'password' => 'password', 'is_active' => false]);

        $response = $this->post(route('staff.login.attempt'), [
            'farm_name' => 'Kebun A',
            'username' => 'anton',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('farm_name');
    }

    public function test_staff_can_logout(): void
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $response = $this->actingAs($staff, 'staff')->post(route('staff.logout'));

        $response->assertRedirect(route('staff.login'));
        $this->assertGuest('staff');
    }

    public function test_staff_cannot_access_user_dashboard(): void
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $this->actingAs($staff, 'staff');
        $this->app['auth']->setDefaultDriver('web');

        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }
}
