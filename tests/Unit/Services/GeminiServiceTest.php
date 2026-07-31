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
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'Halo, ada yang bisa dibantu?'],
                ]],
            ], 200),
        ]);

        $result = app(GeminiService::class)->generate([
            ['role' => 'user', 'content' => 'Halo'],
        ]);

        $this->assertSame('Halo, ada yang bisa dibantu?', $result['text']);
        $this->assertSame([], $result['function_calls']);
    }

    #[Test]
    public function generate_parses_function_calls(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => 'get_farms', 'arguments' => '{"farm_id": 1}'],
                            'extra_content' => ['google' => ['thought_signature' => 'sig-123']],
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $result = app(GeminiService::class)->generate([
            ['role' => 'user', 'content' => 'Farm saya apa saja?'],
        ]);

        $this->assertNull($result['text']);
        $this->assertSame(
            [['id' => 'call_1', 'name' => 'get_farms', 'args' => ['farm_id' => 1], 'signature' => 'sig-123']],
            $result['function_calls'],
        );
    }

    #[Test]
    public function generate_includes_tool_declarations_in_request(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'ok'],
                ]],
            ], 200),
        ]);

        app(GeminiService::class)->generate([
            ['role' => 'user', 'content' => 'halo'],
        ]);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return ($body['tools'][0]['type'] ?? null) === 'function'
                && ($body['tools'][0]['function']['name'] ?? null) === 'get_farms';
        });
    }

    #[Test]
    public function generate_throws_when_api_key_missing(): void
    {
        config(['gemini.api_key' => '']);

        $this->expectException(RuntimeException::class);

        app(GeminiService::class)->generate([
            ['role' => 'user', 'content' => 'halo'],
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
            ['role' => 'user', 'content' => 'halo'],
        ]);
    }

    #[Test]
    public function generate_sends_api_key_as_bearer_token(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'ok'],
                ]],
            ], 200),
        ]);

        app(GeminiService::class)->generate([
            ['role' => 'user', 'content' => 'halo'],
        ]);

        Http::assertSent(fn ($request): bool => str_contains(
            $request->header('Authorization')[0] ?? '',
            'Bearer test-api-key',
        ));
    }
}
