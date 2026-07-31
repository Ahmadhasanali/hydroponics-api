<?php

namespace Tests\Feature\Chat;

use App\Models\Farm;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gemini.api_key' => 'test-api-key']);
    }

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $this->postJson('/api/chat', ['message' => 'halo'])->assertUnauthorized();
    }

    #[Test]
    public function returns_reply_for_plain_text_answer(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'Selada hidroponik membutuhkan PPM 560-840.'],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/chat', [
            'message' => 'Berapa PPM ideal selada?',
            'history' => [],
        ]);

        $response->assertOk()->assertJson([
            'reply' => 'Selada hidroponik membutuhkan PPM 560-840.',
        ]);
    }

    #[Test]
    public function executes_function_calling_loop_and_returns_final_reply(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        Tank::factory()->create([
            'farm_id' => $farm->id,
            'created_by' => $user->id,
            'name' => 'Tank Selada A',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => ['name' => 'get_farms', 'arguments' => '{}'],
                                'extra_content' => ['google' => ['thought_signature' => 'sig-123']],
                            ]],
                        ],
                    ]],
                ], 200)
                ->push([
                    'choices' => [[
                        'message' => ['role' => 'assistant', 'content' => 'Farm Anda bernama '.$farm->name.'.'],
                    ]],
                ], 200)
                ->whenEmpty(Http::response([], 500)),
        ]);

        $response = $this->actingAs($user)->postJson('/api/chat', [
            'message' => 'Farm saya apa saja?',
            'history' => [],
        ]);

        $response->assertOk()->assertJson(['reply' => 'Farm Anda bernama '.$farm->name.'.']);
        $this->assertCount(2, Http::recorded());

        Http::assertSent(function ($request) use ($farm): bool {
            $body = $request->data();
            $messages = $body['messages'];
            $last = $messages[count($messages) - 1];
            $assistant = $messages[count($messages) - 2];

            return ($last['role'] ?? null) === 'tool'
                && ($last['tool_call_id'] ?? null) === 'call_1'
                && str_contains($last['content'] ?? '', $farm->name)
                && ($assistant['role'] ?? null) === 'assistant'
                && ($assistant['tool_calls'][0]['function']['arguments'] ?? null) === '{}'
                && ($assistant['tool_calls'][0]['extra_content']['google']['thought_signature'] ?? null) === 'sig-123';
        });
    }

    #[Test]
    public function returns_503_with_friendly_message_when_gemini_fails(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/chat', [
            'message' => 'halo',
            'history' => [],
        ]);

        $response->assertStatus(503)->assertJson([
            'reply' => 'Maaf, layanan AI sedang sibuk. Silakan coba lagi sebentar.',
        ]);
    }

    #[Test]
    public function normalizes_history_starting_with_assistant_turn(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'Halo juga!'],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/chat', [
            'message' => 'halo',
            'history' => [['role' => 'assistant', 'content' => 'Halo!']],
        ]);

        $response->assertOk()->assertJson(['reply' => 'Halo juga!']);

        Http::assertSent(function ($request): bool {
            return $request->data()['messages'][1]['role'] === 'user';
        });
    }

    #[Test]
    public function accepts_long_assistant_history_content(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'Dipahami.'],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/chat', [
            'message' => 'lanjut',
            'history' => [['role' => 'assistant', 'content' => str_repeat('a', 3000)]],
        ]);

        $response->assertOk()->assertJson(['reply' => 'Dipahami.']);
    }

    #[Test]
    public function rate_limits_after_ten_messages_per_minute(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'ok'],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson('/api/chat', ['message' => 'halo', 'history' => []])
                ->assertOk();
        }

        $this->actingAs($user)->postJson('/api/chat', ['message' => 'halo', 'history' => []])
            ->assertStatus(429);
    }
}
