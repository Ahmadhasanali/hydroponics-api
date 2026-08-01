<?php

namespace Tests\Feature\Frontend;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PwaLayoutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function layout_renders_pwa_meta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertSee('theme-color', false);
    }
}
