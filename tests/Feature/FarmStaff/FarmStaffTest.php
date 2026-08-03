<?php

namespace Tests\Feature\FarmStaff;

use App\Models\Farm;
use App\Models\Farm\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FarmStaffTest extends TestCase
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

    public function test_owner_can_create_staff(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();

        $response = $this->actingAs($owner)->post(route('farm.members.staff-store', $farm), [
            'name' => 'Anton',
            'username' => 'anton',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('farm.members.index', $farm));
        $this->assertDatabaseHas('staff', [
            'farm_id' => $farm->id,
            'username' => 'anton',
        ]);
    }

    public function test_manager_can_create_staff(): void
    {
        ['farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($manager)->post(route('farm.members.staff-store', $farm), [
            'name' => 'Anton',
            'username' => 'anton',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('staff', ['farm_id' => $farm->id, 'username' => 'anton']);
    }

    public function test_username_unique_per_farm_on_create(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        Staff::factory()->create(['farm_id' => $farm->id, 'username' => 'anton']);

        $response = $this->actingAs($owner)->post(route('farm.members.staff-store', $farm), [
            'name' => 'Anton Lain',
            'username' => 'anton',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_owner_can_deactivate_and_reactivate_staff(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $this->actingAs($owner)->put(route('farm.members.staff-toggle', [$farm, $staff]));
        $this->assertFalse($staff->fresh()->is_active);

        $this->actingAs($owner)->put(route('farm.members.staff-toggle', [$farm, $staff]));
        $this->assertTrue($staff->fresh()->is_active);
    }

    public function test_owner_can_reset_staff_password(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $response = $this->actingAs($owner)->put(route('farm.members.staff-password', [$farm, $staff]), [
            'password' => 'newsecret123',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('newsecret123', $staff->fresh()->password));
    }

    public function test_owner_can_delete_staff(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $response = $this->actingAs($owner)->delete(route('farm.members.staff-destroy', [$farm, $staff]));

        $response->assertRedirect();
        $this->assertSoftDeleted('staff', ['id' => $staff->id]);
    }

    public function test_plain_member_cannot_manage_staff(): void
    {
        ['farm' => $farm] = $this->setUpFarm();
        $member = User::factory()->create();
        $farm->users()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($member)->post(route('farm.members.staff-store', $farm), [
            'name' => 'X',
            'username' => 'x',
            'password' => 'secret123',
        ]);

        $response->assertForbidden();
    }
}
