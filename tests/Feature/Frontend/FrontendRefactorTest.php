<?php

namespace Tests\Feature\Frontend;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendRefactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_reusable_stat_components(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Temperature');
        $response->assertSee('Konsumsi Nutrisi');
        $response->assertSee('rounded-[2rem]');
        $response->assertDontSee('cdn.jsdelivr.net/npm/bootstrap@5.3.3', false);
    }

    public function test_login_page_is_rendered_with_tailwind_layout(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('bg-[radial-gradient(circle_at_top_left', false);
        $response->assertSee('Masuk Sekarang');
        $response->assertDontSee('bootstrap.min.css', false);
    }
}
