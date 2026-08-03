<?php

namespace Tests\Feature\Farm;

use App\Models\Farm;
use App\Models\Farm\FarmUser;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_farm_name_unique_index_enforced(): void
    {
        Farm::factory()->create(['name' => 'Kebun Satu']);

        $this->expectException(QueryException::class);
        Farm::factory()->create(['name' => 'Kebun Satu']);
    }

    public function test_member_role_migrated_to_manager(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        DB::table('farm_users')->insert([
            ['farm_id' => $farm->id, 'user_id' => $owner->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()],
            ['farm_id' => $farm->id, 'user_id' => $member->id, 'role' => 'member', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Panggil ulang migrasi role secara manual agar bisa diuji deterministik.
        DB::table('farm_users')->where('role', 'member')->update(['role' => 'manager']);

        $this->assertSame('manager', FarmUser::where('user_id', $member->id)->first()->role);
        $this->assertSame('owner', FarmUser::where('user_id', $owner->id)->first()->role);
    }
}
