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
            $tools = $body['tools'] ?? [];

            foreach ($tools as $tool) {
                if (($tool['type'] ?? null) === 'function'
                    && ($tool['function']['name'] ?? null) === 'get_farms') {
                    return true;
                }
            }

            return false;
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

    #[Test]
    public function generate_fails_over_to_next_model_on_rate_limit(): void
    {
        config([
            'gemini.api_key' => 'test-api-key',
            'gemini.models' => ['gemini-3.6-flash', 'gemini-3.5-flash'],
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'rate limit']], 429)
                ->push([
                    'choices' => [[
                        'message' => ['role' => 'assistant', 'content' => 'ok dari model kedua'],
                    ]],
                ], 200)
                ->whenEmpty(Http::response([], 500)),
        ]);

        $result = app(GeminiService::class)->generate([
            ['role' => 'user', 'content' => 'halo'],
        ]);

        $this->assertSame('ok dari model kedua', $result['text']);

        $requests = Http::recorded();
        $this->assertCount(2, $requests);
        $this->assertSame('gemini-3.6-flash', $requests[0][0]->data()['model']);
        $this->assertSame('gemini-3.5-flash', $requests[1][0]->data()['model']);
    }

    #[Test]
    public function generate_throws_when_all_models_exhausted(): void
    {
        config([
            'gemini.api_key' => 'test-api-key',
            'gemini.models' => ['gemini-3.6-flash', 'gemini-3.5-flash'],
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'rate limit']], 429)
                ->push(['error' => ['message' => 'rate limit']], 429)
                ->whenEmpty(Http::response([], 500)),
        ]);

        $this->expectException(RuntimeException::class);

        app(GeminiService::class)->generate([
            ['role' => 'user', 'content' => 'halo'],
        ]);

        $this->assertCount(2, Http::recorded());
    }

    #[Test]
    public function generate_does_not_fail_over_on_client_error(): void
    {
        config([
            'gemini.api_key' => 'test-api-key',
            'gemini.models' => ['gemini-3.6-flash', 'gemini-3.5-flash'],
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'bad request']], 400)
                ->whenEmpty(Http::response([], 500)),
        ]);

        $this->expectException(RuntimeException::class);

        app(GeminiService::class)->generate([
            ['role' => 'user', 'content' => 'halo'],
        ]);

        $this->assertCount(1, Http::recorded());
    }
}
