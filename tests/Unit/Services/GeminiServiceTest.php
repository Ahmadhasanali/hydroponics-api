<?php

namespace Tests\Unit\Services;

use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['gemini.api_key' => 'test-api-key']);
    }

    #[Test]
    public function generate_returns_text_from_response(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Halo, ada yang bisa dibantu?']]],
                ]],
            ], 200),
        ]);

        $result = app(GeminiService::class)->generate([
            ['role' => 'user', 'parts' => [['text' => 'Halo']]],
        ]);

        $this->assertSame('Halo, ada yang bisa dibantu?', $result['text']);
        $this->assertSame([], $result['function_calls']);
    }

    #[Test]
    public function generate_parses_function_calls(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'functionCall' => ['name' => 'get_farms', 'args' => ['farm_id' => 1]],
                    ]]],
                ]],
            ], 200),
        ]);

        $result = app(GeminiService::class)->generate([
            ['role' => 'user', 'parts' => [['text' => 'Farm saya apa saja?']]],
        ]);

        $this->assertNull($result['text']);
        $this->assertSame([['name' => 'get_farms', 'args' => ['farm_id' => 1]]], $result['function_calls']);
    }

    #[Test]
    public function generate_includes_tool_declarations_in_request(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);

        app(GeminiService::class)->generate([
            ['role' => 'user', 'parts' => [['text' => 'halo']]],
        ]);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return isset($body['tools'][0]['functionDeclarations'])
                && collect($body['tools'][0]['functionDeclarations'])->contains('name', 'get_farms');
        });
    }

    #[Test]
    public function generate_throws_when_api_key_missing(): void
    {
        config(['gemini.api_key' => '']);

        $this->expectException(RuntimeException::class);

        app(GeminiService::class)->generate([
            ['role' => 'user', 'parts' => [['text' => 'halo']]],
        ]);
    }

    #[Test]
    public function generate_throws_on_api_error(): void
    {
        config(['gemini.api_key' => 'test-api-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $this->expectException(RuntimeException::class);

        app(GeminiService::class)->generate([
            ['role' => 'user', 'parts' => [['text' => 'halo']]],
        ]);
    }
}
