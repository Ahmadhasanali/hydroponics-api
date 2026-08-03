<?php

namespace Tests\Feature\Farm;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class FarmPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function farmWithOwner(): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);

        return compact('owner', 'farm');
    }

    public function test_manager_can_update_farm(): void
    {
        ['farm' => $farm] = $this->farmWithOwner();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $this->assertTrue(Gate::forUser($manager)->allows('update', $farm));
        $this->assertTrue(Gate::forUser($manager)->allows('manageStaff', $farm));
        $this->assertTrue(Gate::forUser($manager)->allows('manageMembers', $farm));
    }

    public function test_manager_cannot_delete_or_transfer(): void
    {
        ['farm' => $farm] = $this->farmWithOwner();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $this->assertTrue(Gate::forUser($manager)->denies('delete', $farm));
        $this->assertTrue(Gate::forUser($manager)->denies('transferOwnership', $farm));
    }

    public function test_owner_can_do_everything(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->farmWithOwner();

        $this->assertTrue(Gate::forUser($owner)->allows('update', $farm));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $farm));
        $this->assertTrue(Gate::forUser($owner)->allows('transferOwnership', $farm));
        $this->assertTrue(Gate::forUser($owner)->allows('manageMembers', $farm));
    }

    public function test_unrelated_user_cannot_view_farm(): void
    {
        ['farm' => $farm] = $this->farmWithOwner();
        $stranger = User::factory()->create();

        $this->assertTrue(Gate::forUser($stranger)->denies('view', $farm));
    }
}
