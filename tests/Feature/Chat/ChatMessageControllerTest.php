<?php

namespace Tests\Feature\Chat;

use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatMessageControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function returns_messages_in_order(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->for($user)->create();
        ChatMessage::factory()->for($session)->create(['role' => 'user', 'content' => 'Halo']);
        ChatMessage::factory()->for($session)->create(['role' => 'assistant', 'content' => 'Hai!']);

        $this->actingAs($user)->getJson("/api/chat/sessions/{$session->id}/messages")
            ->assertOk()
            ->assertJsonPath('messages.0.content', 'Halo')
            ->assertJsonPath('messages.1.content', 'Hai!');
    }

    #[Test]
    public function cannot_read_other_users_session_messages(): void
    {
        $other = User::factory()->create();
        $session = ChatSession::factory()->for($other)->create();
        ChatMessage::factory()->for($session)->create();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/chat/sessions/{$session->id}/messages")
            ->assertNotFound();
    }

    #[Test]
    public function migrates_localstorage_history_once(): void
    {
        $user = User::factory()->create();
        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => 'Apa itu hidroponik?'],
                ['role' => 'assistant', 'content' => 'Hidroponik adalah ...'],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/chat/sessions/migrate', $payload);

        $response->assertOk()->assertJsonPath('migrated', true);
        $sessionId = $response->json('session.id');
        $this->assertDatabaseHas('chat_messages', ['chat_session_id' => $sessionId, 'role' => 'user', 'content' => 'Apa itu hidroponik?']);
        $this->assertDatabaseHas('chat_messages', ['chat_session_id' => $sessionId, 'role' => 'assistant']);

        $again = $this->actingAs($user)->postJson('/api/chat/sessions/migrate', $payload);
        $again->assertOk()->assertJsonPath('migrated', false);
        $this->assertDatabaseCount('chat_sessions', 1);
    }

    #[Test]
    public function migrate_validates_payload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/chat/sessions/migrate', [
            'messages' => [
                ['role' => 'system', 'content' => 'x'],
            ],
        ])->assertUnprocessable();

        $this->actingAs($user)->postJson('/api/chat/sessions/migrate', [
            'messages' => array_fill(0, 21, ['role' => 'user', 'content' => 'x']),
        ])->assertUnprocessable();
    }
}
