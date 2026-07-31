<?php

namespace Tests\Feature\Frontend;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatWidgetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function chat_widget_renders_on_authenticated_pages(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Agro Bot');
        $response->assertSee('agroBotToggle');
        $response->assertSee(route('chat.send'));
    }

    #[Test]
    public function chat_widget_is_absent_on_login_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('Agro Bot');
    }
}
