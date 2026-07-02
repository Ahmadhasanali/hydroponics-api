<?php

namespace Tests\Feature\Frontend;

use App\Models\Farm;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendRefactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_reusable_stat_components(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Total Tank');
        $response->assertSee('Aksi Cepat');
        $response->assertSee('rounded-[2rem]');
        $response->assertDontSee('cdn.jsdelivr.net/npm/bootstrap@5.3.3', false);
    }

    public function test_login_page_is_rendered_with_tailwind_layout(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('bg-[radial-gradient(circle_at_top_left', false);
        $response->assertSee('Selamat Datang');
        $response->assertDontSee('bootstrap.min.css', false);
    }
}
