<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\HasApiTokens;
use Tests\TestCase;

class AuthSetupTest extends TestCase
{
    public function test_api_guard_uses_sanctum_driver(): void
    {
        $this->assertSame('sanctum', config('auth.guards.api.driver'));
        $this->assertSame('users', config('auth.guards.api.provider'));
    }

    public function test_user_model_uses_has_api_tokens_trait(): void
    {
        $traits = class_uses_recursive(User::class);

        $this->assertContains(HasApiTokens::class, $traits);
    }

    public function test_api_routes_file_is_registered(): void
    {
        $exitCode = Artisan::call('route:list', ['--path' => 'api']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('api/user', $output);

        $route = $this->app['router']->getRoutes()->getByName('api.user')
            ?? collect($this->app['router']->getRoutes()->getRoutes())
                ->first(fn ($r) => $r->uri() === 'api/user');

        $this->assertNotNull($route, 'Expected GET /api/user route to be registered');
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
    }
}
