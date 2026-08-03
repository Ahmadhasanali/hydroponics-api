<?php

namespace Tests\Feature\Farm;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NoFarmEmptyStateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_no_farm_page_renders_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->view('farm.no-farm')
            ->assertSee('Belum Ada Farm')
            ->assertSee('Buat Farm Baru');
    }
}
