<?php

namespace Tests\Feature\Farm;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FarmTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createOwner(): array
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);

        return compact('user', 'farm');
    }

    /** @see FR #3 - Farm Management: View Farms */
    public function test_user_can_view_farms_list(): void
    {
        ['user' => $user, 'farm' => $farm] = $this->createOwner();

        $response = $this->actingAs($user)->get(route('farm.index'));

        $response->assertOk();
        $response->assertSee($farm->name);
    }

    /** @see FR #3 - Farm Management: Create Farm */
    public function test_user_can_create_farm(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('farm.store'), [
            'name' => 'Farm Baru',
            'address' => 'Jl. Hidroponik No.1',
            'description' => 'Deskripsi farm',
        ]);

        $response->assertRedirect(route('farm.index'));
        $this->assertDatabaseHas('farms', ['name' => 'Farm Baru']);
    }

    /** @see FR #3 - Farm Management: Creator becomes owner */
    public function test_farm_creator_is_owner(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('farm.store'), [
            'name' => 'Farm Saya',
        ]);

        $farm = Farm::where('name', 'Farm Saya')->first();
        $this->assertDatabaseHas('farm_users', [
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        $this->assertEquals($farm->id, session('selected_farm_id'));
    }

    /** @see FR #3 - Farm Management: View Farms */
    public function test_user_can_view_own_farm(): void
    {
        ['user' => $user, 'farm' => $farm] = $this->createOwner();

        $response = $this->actingAs($user)->get(route('farm.show', $farm));

        $response->assertOk();
        $response->assertSee($farm->name);
    }

    /** @see FR #3 - Farm Management: Edit Farm (owner only) */
    public function test_owner_can_update_farm(): void
    {
        ['user' => $user, 'farm' => $farm] = $this->createOwner();

        $response = $this->actingAs($user)->put(route('farm.update', $farm), [
            'name' => 'Updated Farm',
            'address' => 'Alamat Baru',
        ]);

        $response->assertRedirect(route('farm.index'));
        $this->assertDatabaseHas('farms', ['id' => $farm->id, 'name' => 'Updated Farm']);
    }

    /** @see FR #3 - Farm Management: Only owner can edit */
    public function test_non_owner_cannot_update_farm(): void
    {
        ['farm' => $farm] = $this->createOwner();
        $member = User::factory()->create();
        $farm->users()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($member)->put(route('farm.update', $farm), [
            'name' => 'Hacked Name',
        ]);

        $response->assertForbidden();
    }

    /** @see FR #3 - Farm Management: Delete Farm (owner only) */
    public function test_owner_can_delete_farm(): void
    {
        ['user' => $user, 'farm' => $farm] = $this->createOwner();
        session()->put('selected_farm_id', $farm->id);

        $response = $this->actingAs($user)->delete(route('farm.destroy', $farm));

        $response->assertRedirect(route('farm.index'));
        $this->assertSoftDeleted($farm);
        $this->assertNull(session('selected_farm_id'));
    }

    /** @see FR #3 - Farm Management: Only owner can delete */
    public function test_non_owner_cannot_delete_farm(): void
    {
        ['farm' => $farm] = $this->createOwner();
        $member = User::factory()->create();
        $farm->users()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($member)->delete(route('farm.destroy', $farm));

        $response->assertForbidden();
    }
}
