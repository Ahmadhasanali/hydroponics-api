<?php

namespace Tests\Feature\Api;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FarmPolicySalesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_and_manager_can_manage_sales(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        $farm->users()->attach($manager->id, ['role' => 'manager']);
        $farm->users()->attach($member->id, ['role' => 'member']);

        $this->assertTrue($owner->can('manageSales', $farm));
        $this->assertTrue($manager->can('manageSales', $farm));
        $this->assertFalse($member->can('manageSales', $farm));
        $this->assertTrue($owner->can('viewSales', $farm));
        $this->assertFalse($member->can('viewSales', $farm));
    }
}
