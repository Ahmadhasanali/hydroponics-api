<?php

namespace Tests\Feature\Chat;

use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatSessionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $this->getJson('/api/v1/chat/sessions')->assertUnauthorized();
    }

    #[Test]
    public function lists_sessions_ordered_by_updated_at(): void
    {
        $user = User::factory()->create();
        $older = ChatSession::factory()->for($user)->create(['title' => 'Tua']);
        $newer = ChatSession::factory()->for($user)->create(['title' => 'Baru']);
        ChatMessage::factory()->for($older)->create();
        $older->touch();

        $this->actingAs($user)->getJson('/api/v1/chat/sessions')
            ->assertOk()
            ->assertJsonPath('sessions.0.title', 'Tua')
            ->assertJsonPath('sessions.0.messages_count', 1)
            ->assertJsonPath('sessions.1.title', 'Baru');
    }

    #[Test]
    public function excludes_soft_deleted_sessions(): void
    {
        $user = User::factory()->create();
        ChatSession::factory()->for($user)->create(['title' => 'Hidup']);
        $trashed = ChatSession::factory()->for($user)->create(['title' => 'Sampah']);
        $trashed->delete();

        $this->actingAs($user)->getJson('/api/v1/chat/sessions')
            ->assertOk()
            ->assertJsonCount(1, 'sessions')
            ->assertJsonMissing(['title' => 'Sampah']);
    }

    #[Test]
    public function does_not_show_other_users_sessions(): void
    {
        $other = User::factory()->create();
        ChatSession::factory()->for($other)->create();

        $this->actingAs(User::factory()->create())->getJson('/api/v1/chat/sessions')
            ->assertOk()
            ->assertJsonCount(0, 'sessions');
    }

    #[Test]
    public function creates_untitled_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/chat/sessions')
            ->assertCreated()
            ->assertJsonPath('session.title', null);

        $this->assertDatabaseHas('chat_sessions', ['user_id' => $user->id]);
    }

    #[Test]
    public function renames_session(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->for($user)->create();

        $this->actingAs($user)->patchJson("/api/v1/chat/sessions/{$session->id}", ['title' => 'Panen Selada'])
            ->assertOk()
            ->assertJsonPath('session.title', 'Panen Selada');

        $this->assertDatabaseHas('chat_sessions', ['id' => $session->id, 'title' => 'Panen Selada']);
    }

    #[Test]
    public function rejects_blank_or_too_long_title(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->for($user)->create();

        $this->actingAs($user)->patchJson("/api/v1/chat/sessions/{$session->id}", ['title' => ''])
            ->assertUnprocessable();
        $this->actingAs($user)->patchJson("/api/v1/chat/sessions/{$session->id}", ['title' => str_repeat('a', 61)])
            ->assertUnprocessable();
    }

    #[Test]
    public function cannot_modify_other_users_session(): void
    {
        $other = User::factory()->create();
        $session = ChatSession::factory()->for($other)->create();

        $this->actingAs(User::factory()->create())
            ->patchJson("/api/v1/chat/sessions/{$session->id}", ['title' => 'Bajak'])
            ->assertNotFound();
        $this->actingAs(User::factory()->create())
            ->deleteJson("/api/v1/chat/sessions/{$session->id}")
            ->assertNotFound();
    }

    #[Test]
    public function soft_deletes_session(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->for($user)->create();

        $this->actingAs($user)->deleteJson("/api/v1/chat/sessions/{$session->id}")->assertNoContent();

        $this->assertSoftDeleted('chat_sessions', ['id' => $session->id]);
    }
}
