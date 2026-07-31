<?php

namespace Tests\Feature\Chat;

use App\Models\Chat\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatPersistenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gemini.api_key' => 'test-api-key']);
    }

    private function fakeReply(string $text): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => $text],
                ]],
            ], 200),
        ]);
    }

    #[Test]
    public function creates_session_and_persists_exchange_without_session_id(): void
    {
        $this->fakeReply('Halo! Silakan tanya.');

        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/api/chat', [
            'message' => 'Halo bot',
        ]);

        $response->assertOk()
            ->assertJsonPath('reply', 'Halo! Silakan tanya.')
            ->assertJsonStructure(['session_id']);

        $sessionId = $response->json('session_id');
        $this->assertDatabaseHas('chat_messages', ['chat_session_id' => $sessionId, 'role' => 'user', 'content' => 'Halo bot']);
        $this->assertDatabaseHas('chat_messages', ['chat_session_id' => $sessionId, 'role' => 'assistant', 'content' => 'Halo! Silakan tanya.']);
    }

    #[Test]
    public function auto_titles_session_from_first_message(): void
    {
        $this->fakeReply('ok');

        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/api/chat', [
            'message' => 'Bagaimana cara menanam selada hidroponik di rumah untuk pemula?',
        ]);

        $this->assertDatabaseHas('chat_sessions', [
            'id' => $response->json('session_id'),
            'title' => 'Bagaimana cara menanam selada hidroponik di rumah untuk pemu',
        ]);
    }

    #[Test]
    public function reuses_session_and_builds_context_from_database(): void
    {
        Http::fake(function ($request) {
            $body = $request->data();
            $context = array_map(fn (array $m): string => $m['role'].':'.$m['content'], $body['messages']);
            $hasOld = in_array('user:Berapa PPM ideal selada?', $context, true);
            $hasNew = in_array('user:Dan pH-nya?', $context, true);

            return Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => $hasOld && $hasNew ? 'Konteks benar' : 'Konteks hilang',
                    ],
                ]],
            ], 200);
        });

        $user = User::factory()->create();
        $session = ChatSession::factory()->for($user)->create();
        $session->messages()->createMany([
            ['role' => 'user', 'content' => 'Berapa PPM ideal selada?'],
            ['role' => 'assistant', 'content' => '560-840 PPM.'],
        ]);

        $response = $this->actingAs($user)->postJson('/api/chat', [
            'session_id' => $session->id,
            'message' => 'Dan pH-nya?',
        ]);

        $response->assertOk()->assertJsonPath('reply', 'Konteks benar');
        $this->assertSame($session->id, $response->json('session_id'));
        $this->assertDatabaseCount('chat_messages', 4);
    }

    #[Test]
    public function does_not_persist_when_gemini_fails(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('{}', 500)]);

        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/chat', ['message' => 'halo'])
            ->assertStatus(503);

        $this->assertDatabaseCount('chat_messages', 0);
    }
}
