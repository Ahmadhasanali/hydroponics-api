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
                'candidates' => [['content' => ['parts' => [['text' => 'Selada hidroponik membutuhkan PPM 560-840.']]]]],
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
        $tank = Tank::factory()->create([
            'farm_id' => $farm->id,
            'created_by' => $user->id,
            'name' => 'Tank Selada A',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'functionCall' => ['name' => 'get_farms', 'args' => []],
                        ]]],
                    ]],
                ], 200)
                ->push([
                    'candidates' => [['content' => ['parts' => [['text' => 'Farm Anda bernama '.$farm->name.'.']]]]],
                ], 200)
                ->whenEmpty(Http::response([], 500)),
        ]);

        $response = $this->actingAs($user)->postJson('/api/chat', [
            'message' => 'Farm saya apa saja?',
            'history' => [],
        ]);

        $response->assertOk()->assertJson(['reply' => 'Farm Anda bernama '.$farm->name.'.']);
        $this->assertCount(2, Http::recorded());

        Http::assertSent(function ($request): bool {
            $body = $request->data();
            $last = $body['contents'][count($body['contents']) - 1];
            $parts = $last['parts'] ?? [];

            return isset($parts[0]['functionResponse'])
                && $parts[0]['functionResponse']['name'] === 'get_farms'
                && isset($parts[0]['functionResponse']['response']['data'][0]['name']);
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
    public function rate_limits_after_ten_messages_per_minute(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
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
