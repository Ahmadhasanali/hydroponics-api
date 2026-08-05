<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Farm\FarmController;
use App\Http\Controllers\Farm\FarmStaffController;
use App\Http\Controllers\FarmUserController;
use App\Models\Farm;
use App\Models\Farm\ActivityLog;
use App\Models\Farm\FarmUser;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FarmApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $withBinding = fn ($route) => $route->middleware(SubstituteBindings::class);

        $withBinding(Route::get('api/v1/farms', [FarmController::class, 'index']));
        $withBinding(Route::post('api/v1/farms', [FarmController::class, 'store']));
        $withBinding(Route::get('api/v1/farms/{farm}', [FarmController::class, 'show']));
        $withBinding(Route::put('api/v1/farms/{farm}', [FarmController::class, 'update']));
        $withBinding(Route::delete('api/v1/farms/{farm}', [FarmController::class, 'destroy']));
        $withBinding(Route::post('api/v1/farms/{farm}/transfer-ownership', [FarmController::class, 'transferOwnership']));

        $withBinding(Route::post('api/v1/farms/{farm}/members', [FarmUserController::class, 'store']));
        $withBinding(Route::delete('api/v1/farms/{farm}/members/{farmUser}', [FarmUserController::class, 'destroy']));

        $withBinding(Route::post('api/v1/farms/{farm}/staff', [FarmStaffController::class, 'store']));
        $withBinding(Route::put('api/v1/farms/{farm}/staff/{staff}/password', [FarmStaffController::class, 'resetPassword']));
        $withBinding(Route::patch('api/v1/farms/{farm}/staff/{staff}/toggle', [FarmStaffController::class, 'toggle']));
        $withBinding(Route::delete('api/v1/farms/{farm}/staff/{staff}', [FarmStaffController::class, 'destroy']));
    }

    private function attach(User $user, Farm $farm, string $role): void
    {
        $user->farms()->attach($farm, ['role' => $role]);
    }

    private function farmUserId(Farm $farm, User $user): int
    {
        return FarmUser::where('farm_id', $farm->id)
            ->where('user_id', $user->id)
            ->firstOrFail()
            ->id;
    }

    #[Test]
    public function index_returns_farms_with_tanks_count(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $this->attach($user, $farm, 'owner');
        Tank::factory()->count(2)->create(['farm_id' => $farm->id, 'created_by' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/farms');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.farms')
            ->assertJsonPath('data.farms.0.id', $farm->id)
            ->assertJsonPath('data.farms.0.tanks_count', 2);
    }

    #[Test]
    public function store_creates_farm_and_attaches_owner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/farms', [
            'name' => 'Farm Hidroponik Baru',
            'address' => 'Jl. Merdeka No. 1',
            'description' => 'Kebun contoh',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.farm.name', 'Farm Hidroponik Baru')
            ->assertJsonPath('data.farm.created_by', $user->id);

        $this->assertDatabaseHas('farms', [
            'name' => 'Farm Hidroponik Baru',
            'created_by' => $user->id,
        ]);
        $this->assertDatabaseHas('farm_users', [
            'farm_id' => $response->json('data.farm.id'),
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    #[Test]
    public function show_returns_farm_with_tanks_and_members(): void
    {
        $user = User::factory()->create();
        $member = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $this->attach($user, $farm, 'owner');
        $this->attach($member, $farm, 'manager');
        Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/farms/{$farm->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.farm.id', $farm->id)
            ->assertJsonCount(1, 'data.farm.tanks')
            ->assertJsonCount(2, 'data.farm.users');
    }

    #[Test]
    public function update_requires_owner_or_manager_policy(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $this->attach($user, $farm, 'owner');

        $response = $this->actingAs($user)->putJson("/api/v1/farms/{$farm->id}", [
            'name' => 'Farm Diperbarui',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.farm.name', 'Farm Diperbarui');

        $this->assertDatabaseHas('farms', ['id' => $farm->id, 'name' => 'Farm Diperbarui']);
    }

    #[Test]
    public function update_denies_user_not_attached_to_farm(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $this->attach($owner, $farm, 'owner');

        $response = $this->actingAs($outsider)->putJson("/api/v1/farms/{$farm->id}", [
            'name' => 'Hack',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(403);

        $this->assertDatabaseHas('farms', ['id' => $farm->id, 'name' => $farm->name]);
    }

    #[Test]
    public function destroy_deletes_farm_tanks_and_activity_logs(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $this->attach($user, $farm, 'owner');
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);
        $log = ActivityLog::create([
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'action' => 'farm.delete',
            'entity_type' => 'management',
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/farms/{$farm->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Farm berhasil dihapus.');

        $this->assertSoftDeleted('farms', ['id' => $farm->id]);
        $this->assertSoftDeleted('tanks', ['id' => $tank->id]);
        $this->assertDatabaseMissing('activity_logs', ['id' => $log->id]);
    }

    #[Test]
    public function destroy_denies_manager(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $this->attach($owner, $farm, 'owner');
        $this->attach($manager, $farm, 'manager');

        $response = $this->actingAs($manager)->deleteJson("/api/v1/farms/{$farm->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('farms', ['id' => $farm->id, 'deleted_at' => null]);
    }

    #[Test]
    public function transfer_ownership_rejects_self_transfer(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $this->attach($user, $farm, 'owner');

        $response = $this->actingAs($user)->postJson("/api/v1/farms/{$farm->id}/transfer-ownership", [
            'new_owner_id' => $user->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('new_owner_id');

        $this->assertDatabaseHas('farm_users', [
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    #[Test]
    public function transfer_ownership_swaps_roles(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $this->attach($owner, $farm, 'owner');
        $this->attach($member, $farm, 'manager');

        $response = $this->actingAs($owner)->postJson("/api/v1/farms/{$farm->id}/transfer-ownership", [
            'new_owner_id' => $member->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Kepemilikan kebun berhasil ditransfer.');

        $this->assertDatabaseHas('farm_users', [
            'farm_id' => $farm->id,
            'user_id' => $member->id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('farm_users', [
            'farm_id' => $farm->id,
            'user_id' => $owner->id,
            'role' => 'manager',
        ]);
    }

    #[Test]
    public function member_store_adds_member_as_manager(): void
    {
        $owner = User::factory()->create();
        $newMember = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $this->attach($owner, $farm, 'owner');

        $response = $this->actingAs($owner)->postJson("/api/v1/farms/{$farm->id}/members", [
            'email' => $newMember->name,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Anggota berhasil ditambahkan.');

        $this->assertDatabaseHas('farm_users', [
            'farm_id' => $farm->id,
            'user_id' => $newMember->id,
            'role' => 'manager',
        ]);
    }

    #[Test]
    public function member_destroy_removes_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $this->attach($owner, $farm, 'owner');
        $this->attach($member, $farm, 'manager');

        $farmUserId = $this->farmUserId($farm, $member);

        $response = $this->actingAs($owner)->deleteJson("/api/v1/farms/{$farm->id}/members/{$farmUserId}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Anggota berhasil dihapus.');

        $this->assertDatabaseMissing('farm_users', [
            'id' => $farmUserId,
        ]);
    }

    #[Test]
    public function member_destroy_rejects_removing_owner(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $this->attach($owner, $farm, 'owner');

        $response = $this->actingAs($owner)->deleteJson("/api/v1/farms/{$farm->id}/members/".$this->farmUserId($farm, $owner));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Pemilik kebun tidak dapat dihapus.');
    }

    #[Test]
    public function staff_store_creates_staff(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $this->attach($owner, $farm, 'owner');

        $response = $this->actingAs($owner)->postJson("/api/v1/farms/{$farm->id}/staff", [
            'name' => 'Petugas A',
            'username' => 'petugas-a',
            'password' => 'rahasia123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.staff.username', 'petugas-a');

        $this->assertDatabaseHas('staff', [
            'farm_id' => $farm->id,
            'username' => 'petugas-a',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function staff_toggle_flips_active_status(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $this->attach($owner, $farm, 'owner');
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $response = $this->actingAs($owner)->patchJson("/api/v1/farms/{$farm->id}/staff/{$staff->id}/toggle");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.staff.is_active', false);

        $this->assertDatabaseHas('staff', ['id' => $staff->id, 'is_active' => false]);
    }

    #[Test]
    public function staff_reset_password_updates_password(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $this->attach($owner, $farm, 'owner');
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $response = $this->actingAs($owner)->putJson("/api/v1/farms/{$farm->id}/staff/{$staff->id}/password", [
            'password' => 'rahasia-baru-123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password petugas berhasil direset.');

        $this->assertTrue(Hash::check('rahasia-baru-123', $staff->fresh()->password));
    }

    #[Test]
    public function staff_destroy_removes_staff(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $this->attach($owner, $farm, 'owner');
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $response = $this->actingAs($owner)->deleteJson("/api/v1/farms/{$farm->id}/staff/{$staff->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Akun petugas dihapus.');

        $this->assertSoftDeleted('staff', ['id' => $staff->id]);
    }
}
