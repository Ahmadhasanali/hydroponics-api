<?php

namespace Tests\Feature\Api;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AccountDefaultCreationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_new_farm_gets_default_cash_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/farms', ['name' => 'Kebun Baru'])
            ->assertCreated();

        $farm = Farm::where('name', 'Kebun Baru')->firstOrFail();

        $this->assertDatabaseHas('accounts', [
            'farm_id' => $farm->id,
            'name' => 'Cash',
            'type' => 'cash',
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
