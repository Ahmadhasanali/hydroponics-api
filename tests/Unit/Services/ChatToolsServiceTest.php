<?php

namespace Tests\Unit\Services;

use App\Services\ChatToolsService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatToolsServiceTest extends TestCase
{
    #[Test]
    public function declarations_contain_discovered_tool_definitions(): void
    {
        $declarations = app(ChatToolsService::class)->declarations();

        $this->assertNotEmpty($declarations);

        $farmTool = collect($declarations)->firstWhere('name', 'get_farms');
        $this->assertNotNull($farmTool, 'get_farms tidak ter-discovery');
        $this->assertArrayHasKey('description', $farmTool);
        $this->assertArrayHasKey('parameters', $farmTool);
    }

    #[Test]
    public function handle_returns_error_for_unknown_tool(): void
    {
        $result = app(ChatToolsService::class)->handle('tool_tidak_ada', [], \App\Models\User::factory()->make());

        $this->assertArrayHasKey('error', $result);
    }
}
