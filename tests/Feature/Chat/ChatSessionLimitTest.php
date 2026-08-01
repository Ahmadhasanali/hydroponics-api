<?php

namespace Tests\Feature\Chat;

use App\Models\Chat\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatSessionLimitTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gemini.api_key' => 'test-api-key']);
    }

    #[Test]
    public function creating_session_beyond_limit_soft_deletes_oldest(): void
    {
        $user = User::factory()->create();
        $oldest = ChatSession::factory()->for($user)->create();
        $oldest->forceFill(['updated_at' => now()->subDays(5)])->save();
        for ($i = 0; $i < 49; $i++) {
            ChatSession::factory()->for($user)->create();
        }

        $this->actingAs($user)->postJson('/api/chat/sessions')->assertCreated();

        $this->assertSoftDeleted('chat_sessions', ['id' => $oldest->id]);
        $this->assertSame(50, ChatSession::where('user_id', $user->id)->count());
    }

    #[Test]
    public function purge_command_hard_deletes_sessions_older_than_24_hours(): void
    {
        $user = User::factory()->create();
        $recent = ChatSession::factory()->for($user)->create();
        $recent->delete();
        $recent->forceFill(['deleted_at' => now()->subHour()])->save();
        $stale = ChatSession::factory()->for($user)->create();
        $stale->delete();
        $stale->forceFill(['deleted_at' => now()->subHours(25)])->save();
        $stale->messages()->create(['role' => 'user', 'content' => 'x']);

        $this->artisan('chat:purge-deleted-sessions')->assertSuccessful();

        $this->assertDatabaseHas('chat_sessions', ['id' => $recent->id]);
        $this->assertDatabaseMissing('chat_sessions', ['id' => $stale->id]);
        $this->assertDatabaseMissing('chat_messages', ['chat_session_id' => $stale->id]);
    }
}
