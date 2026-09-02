<?php

namespace Tests\Feature\Telegram;

use App\Models\Farm;
use App\Models\MessagingAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TelegramStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_status_returns_linked_false_when_none(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/telegram/status')
            ->assertOk()
            ->assertJsonPath('data.linked', false);
    }

    public function test_status_returns_linked_true_when_account_exists(): void
    {
        $user = User::factory()->create();
        MessagingAccount::factory()->create(['user_id' => $user->id, 'external_id' => '999']);

        $res = $this->actingAs($user)->getJson('/api/v1/telegram/status')->assertOk();

        $res->assertJsonPath('data.linked', true);
        $this->assertSame('999', $res->json('data.external_id'));
    }

    public function test_status_includes_default_farm_id(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($user->id, ['role' => 'owner']);
        MessagingAccount::factory()->create(['user_id' => $user->id, 'default_farm_id' => $farm->id]);

        $res = $this->actingAs($user)->getJson('/api/v1/telegram/status')->assertOk();

        $this->assertSame($farm->id, $res->json('data.default_farm_id'));
    }

    public function test_default_farm_updates_success(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($user->id, ['role' => 'owner']);
        MessagingAccount::factory()->create(['user_id' => $user->id]);

        $res = $this->actingAs($user)->patchJson('/api/v1/telegram/default-farm', ['farm_id' => $farm->id])->assertOk();

        $res->assertJsonPath('data.default_farm_id', $farm->id);
        $this->assertDatabaseHas('messaging_accounts', ['user_id' => $user->id, 'default_farm_id' => $farm->id]);
    }

    public function test_default_farm_fails_if_not_member(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create();
        MessagingAccount::factory()->create(['user_id' => $user->id]);

        // user tidak tergabung di farm -> authorize viewFinance gagal (403)
        $this->actingAs($user)->patchJson('/api/v1/telegram/default-farm', ['farm_id' => $farm->id])
            ->assertForbidden();
    }

    public function test_default_farm_requires_auth(): void
    {
        $farm = Farm::factory()->create();

        $this->patchJson('/api/v1/telegram/default-farm', ['farm_id' => $farm->id])
            ->assertUnauthorized();
    }

    public function test_default_farm_validation_requires_farm_id(): void
    {
        $user = User::factory()->create();
        MessagingAccount::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->patchJson('/api/v1/telegram/default-farm', [])
            ->assertStatus(422);
    }

    public function test_default_farm_returns_404_when_not_linked(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user)->patchJson('/api/v1/telegram/default-farm', ['farm_id' => $farm->id])
            ->assertNotFound();
    }

    public function test_unlink_removes_account(): void
    {
        $user = User::factory()->create();
        MessagingAccount::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->postJson('/api/v1/telegram/unlink')->assertOk();

        $this->assertDatabaseMissing('messaging_accounts', ['user_id' => $user->id]);
    }

    public function test_unlink_requires_auth(): void
    {
        $this->postJson('/api/v1/telegram/unlink')->assertUnauthorized();
    }

    public function test_status_requires_auth(): void
    {
        $this->getJson('/api/v1/telegram/status')->assertUnauthorized();
    }
}
