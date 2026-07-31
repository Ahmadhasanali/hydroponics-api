<?php

namespace Tests\Unit\Chat;

use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatSessionTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function session_belongs_to_user_and_has_many_messages(): void
    {
        $user = User::factory()->create();
        $session = ChatSession::factory()->for($user)->create();
        $message = ChatMessage::factory()->for($session)->create();

        $this->assertTrue($session->user->is($user));
        $this->assertTrue($session->messages->contains($message));
        $this->assertTrue($message->session->is($session));
    }

    #[Test]
    public function deleting_session_removes_its_messages(): void
    {
        $session = ChatSession::factory()->create();
        ChatMessage::factory()->count(3)->for($session)->create();

        $session->forceDelete();

        $this->assertDatabaseCount('chat_messages', 0);
    }
}
