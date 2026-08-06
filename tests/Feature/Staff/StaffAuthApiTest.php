<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\Staff;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffAuthApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->farm = Farm::factory()->create(['name' => 'Kebun Cabe']);
    }

    public function test_staff_can_login_and_receives_token(): void
    {
        $staff = Staff::factory()->create([
            'farm_id' => $this->farm->id,
            'username' => 'petugas1',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/staff/login', [
            'farm_name' => 'Kebun Cabe',
            'username' => 'petugas1',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'staff' => ['id', 'name', 'username']]])
            ->assertJsonPath('data.staff.id', $staff->id);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        Staff::factory()->create([
            'farm_id' => $this->farm->id,
            'username' => 'petugas1',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/staff/login', [
            'farm_name' => 'Kebun Cabe',
            'username' => 'petugas1',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_login_fails_with_wrong_farm_name(): void
    {
        Staff::factory()->create([
            'farm_id' => $this->farm->id,
            'username' => 'petugas1',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/staff/login', [
            'farm_name' => 'Kebun Tidak Ada',
            'username' => 'petugas1',
            'password' => 'secret123',
        ]);

        $response->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_login_fails_when_account_is_inactive(): void
    {
        Staff::factory()->inactive()->create([
            'farm_id' => $this->farm->id,
            'username' => 'petugas1',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/staff/login', [
            'farm_name' => 'Kebun Cabe',
            'username' => 'petugas1',
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_staff_can_logout(): void
    {
        $staff = Staff::factory()->create(['farm_id' => $this->farm->id]);

        $token = $staff->createToken('staff-token', ['staff'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/staff/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, $staff->tokens()->count());
    }
}
