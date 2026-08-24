<?php

namespace Tests\Feature\Api;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FarmPolicyFinanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->farm = Farm::factory()->create();
    }

    public function test_owner_and_manager_have_finance_access(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $this->farm->users()->attach([$owner->id => ['role' => 'owner'], $manager->id => ['role' => 'manager']]);

        $this->assertTrue($owner->can('viewFinance', $this->farm));
        $this->assertTrue($manager->can('manageFinance', $this->farm));
    }

    public function test_non_member_and_operator_do_not_have_finance_access(): void
    {
        $stranger = User::factory()->create();
        $operator = User::factory()->create();
        $this->farm->users()->attach($operator->id, ['role' => 'operator']);

        $this->assertFalse($stranger->can('viewFinance', $this->farm));
        $this->assertFalse($operator->can('manageFinance', $this->farm));
    }
}
