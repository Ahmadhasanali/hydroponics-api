<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\Staff;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_belongs_to_farm(): void
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $this->assertTrue($staff->farm->is($farm));
    }

    public function test_username_unique_per_farm(): void
    {
        $farmA = Farm::factory()->create();
        $farmB = Farm::factory()->create();
        Staff::factory()->create(['farm_id' => $farmA->id, 'username' => 'anton']);

        $this->expectException(QueryException::class);
        Staff::factory()->create(['farm_id' => $farmA->id, 'username' => 'anton']);
    }

    public function test_username_can_duplicate_across_farms(): void
    {
        $farmA = Farm::factory()->create();
        $farmB = Farm::factory()->create();
        Staff::factory()->create(['farm_id' => $farmA->id, 'username' => 'anton']);

        $staffB = Staff::factory()->create(['farm_id' => $farmB->id, 'username' => 'anton']);

        $this->assertDatabaseHas('staff', ['farm_id' => $farmB->id, 'username' => 'anton']);
    }

    public function test_password_is_hashed(): void
    {
        $staff = Staff::factory()->create(['password' => 'password']);

        $this->assertTrue(Hash::check('password', $staff->password));
    }
}
