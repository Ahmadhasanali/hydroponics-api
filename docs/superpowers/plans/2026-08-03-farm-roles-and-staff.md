# Role Kebun (Owner/Manager) & Petugas Lapangan (Staff) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan sistem role per kebun (owner/manager) dan entitas petugas lapangan (staff) terpisah dengan login, layout, dan scope transaksi sendiri.

**Architecture:** Staff adalah entitas terpisah (`staff` table + guard `staff` + layout `layouts.staff`), terikat 1 kebun dengan username unik per kebun `(farm_id, username)`. Transaksi mencatat `user_id` XOR `staff_id`. Otorisasi user-side diperketat via `FarmPolicy` (owner/manager), staff-side lewat controller staff sendiri dengan cek kepemilikan.

**Tech Stack:** Laravel 13, Blade + Tailwind CSS v4, MySQL (Sail), PHPUnit.

## Global Constraints

- Semua perintah Artisan/Composer/NPM/Test dijalankan via `vendor/bin/sail ...` (mis. `vendor/bin/sail artisan test`).
- Login user menggunakan **email** (bukan nama). Login staff menggunakan **nama kebun + username + password**.
- Tidak ada service layer — logika bisnis di controller dan model (ikuti pola existing).
- Setelah mengubah file PHP, jalankan `vendor/bin/sail bin pint --format agent`.
- Test wajib menulis: happy path, failure path, edge case. Pakai PHPUnit (bukan Pest).
- Ikuti pola penamaan & struktur file yang sudah ada (rute resourceful, controller-centric).
- Role pivot `farm_users.role` hanya bernilai `owner` | `manager` setelah Task 3.
- Setiap catatan transaksi diisi `user_id` ATAU `staff_id` (XOR), tidak keduanya.
- Semua pesan flash & validasi dalam Bahasa Indonesia (ikuti existing).

---

### Task 1: Tabel `staff`, Model, Factory & Guard

**Files:**
- Create: `database/migrations/2026_08_03_000000_create_staff_table.php`
- Create: `app/Models/Farm/Staff.php`
- Create: `database/factories/StaffFactory.php`
- Modify: `config/auth.php`
- Modify: `app/Models/Farm.php`
- Test: `tests/Feature/Staff/StaffModelTest.php`

**Interfaces:**
- Produces: model `App\Models\Farm\Staff` (implements `Authenticatable`, `fillable = ['farm_id','name','username','password','is_active']`, relasi `farm(): BelongsTo`), factory `StaffFactory` (password default `'password'`), guard `staff` di `config/auth.php`, relasi `Farm::staff(): HasMany`.

- [ ] **Step 1: Tulis migration**

`database/migrations/2026_08_03_000000_create_staff_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('username');
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['farm_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
```

- [ ] **Step 2: Tulis model Staff**

`app/Models/Farm/Staff.php`:

```php
<?php

namespace App\Models\Farm;

use App\Models\Farm;
use Database\Factories\Farm\StaffFactory;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model implements Authenticatable
{
    use AuthenticatableTrait, HasFactory, SoftDeletes;

    /** @use HasFactory<StaffFactory> */
    protected $fillable = [
        'farm_id',
        'name',
        'username',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }
}
```

- [ ] **Step 3: Tulis factory**

`database/factories/StaffFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Farm\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password' => Hash::make('password'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
```

Catatan: cek apakah direktori factory ada file `database/factories/Farm/` — pindahkan ke namespace yang sudah dipakai factory lain (ikuti pola file factory existing). Jika `FarmUserFactory` di `Database\Factories\Farm\FarmUserFactory`, tempatkan `StaffFactory` di `Database\Factories\Farm\StaffFactory` dan sesuaikan `use`.

- [ ] **Step 4: Tambah guard `staff` di `config/auth.php`**

Di dalam array `guards`, setelah `web`:

```php
'staff' => [
    'driver' => 'session',
    'provider' => 'staff',
],
```

Di dalam array `providers`, setelah `users`:

```php
'staff' => [
    'driver' => 'eloquent',
    'model' => App\Models\Farm\Staff::class,
],
```

- [ ] **Step 5: Tambah relasi `staff()` di `Farm`**

Di `app/Models/Farm.php`, tambah `use App\Models\Farm\Staff;` dan method:

```php
/**
 * @return HasMany<Staff,Farm>
 */
public function staff(): HasMany
{
    return $this->hasMany(Staff::class);
}
```

- [ ] **Step 6: Tulis test gagal**

`tests/Feature/Staff/StaffModelTest.php`:

```php
<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\Staff;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_belongs_to_farm(): void
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $this->assertTrue($staff->farm->is($farm));
    }

    public function test_username_unique_per_farm(): void
    {
        $farmA = Farm::factory()->create();
        $farmB = Farm::factory()->create();
        Staff::factory()->create(['farm_id' => $farmA->id, 'username' => 'anton']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Staff::factory()->create(['farm_id' => $farmA->id, 'username' => 'anton']);
    }

    public function test_username_can_duplicate_across_farms(): void
    {
        $farmA = Farm::factory()->create();
        $farmB = Farm::factory()->create();
        Staff::factory()->create(['farm_id' => $farmA->id, 'username' => 'anton']);

        $staffB = Staff::factory()->create(['farm_id' => $farmB->id, 'username' => 'anton']);

        $this->assertDatabaseHas('staff', ['farm_id' => $farmB->id, 'username' => 'anton']);
    }

    public function test_password_is_hashed(): void
    {
        $staff = Staff::factory()->create(['password' => 'password']);

        $this->assertTrue(Hash::check('password', $staff->password));
    }
}
```

- [ ] **Step 7: Jalankan test, pastikan gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Staff/StaffModelTest.php`
Expected: gagal (table/model tidak ada).

- [ ] **Step 8: Jalankan migration + implementasi**

Run: `vendor/bin/sail artisan migrate`
Buat file: model `Staff.php`, factory, guard, relasi Farm (langkah 2–5).
Jalankan pint: `vendor/bin/sail bin pint --format agent`

- [ ] **Step 9: Jalankan test, pastikan pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Staff/StaffModelTest.php`
Expected: PASS (4 test).

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_08_03_000000_create_staff_table.php app/Models/Farm/Staff.php database/factories/StaffFactory.php config/auth.php app/Models/Farm.php tests/Feature/Staff/StaffModelTest.php
git commit -m "feat: staff model, factory & guard staff"
```

---

### Task 2: Kolom `staff_id` pada Tabel Transaksi + Relasi Model + Atribusi Tampilan

**Files:**
- Create: `database/migrations/2026_08_03_000001_add_staff_id_to_transaction_tables.php`
- Modify: `app/Models/Farm/DailyMonitoring.php`
- Modify: `app/Models/Farm/NutrientAddition.php`
- Modify: `app/Models/Farm/PhDownLog.php`
- Modify: `app/Models/Farm/ActivityLog.php`
- Modify: `resources/views/daily-monitoring/index.blade.php`
- Modify: `resources/views/nutrient-addition/index.blade.php`
- Modify: `resources/views/ph-down-log/index.blade.php`
- Modify: `resources/views/tank/show.blade.php`
- Modify: `resources/views/activity-logs/index.blade.php`
- Test: `tests/Feature/Staff/StaffAttributionTest.php`

**Interfaces:**
- Consumes: model `App\Models\Farm\Staff` dari Task 1.
- Produces: kolom `staff_id` nullable di `daily_monitorings`, `nutrient_additions`, `ph_down_logs`, `activity_logs`; aksesor `actorName(): ?string` di ketiga model transaksi (mengembalikan `user?->name ?? staff?->name ?? null`); relasi `staff(): BelongsTo` di keempat model.

- [ ] **Step 1: Tulis migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['daily_monitorings', 'nutrient_additions', 'ph_down_logs', 'activity_logs'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->foreignId('staff_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('staff')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['daily_monitorings', 'nutrient_additions', 'ph_down_logs', 'activity_logs'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('staff_id');
            });
        }
    }
};
```

Catatan: pastikan urutan nama file migration > Task 1, dan `activity_logs` memang memiliki kolom `user_id` (cek `database/migrations/2026_07_01_000008_create_activity_logs_table.php`).

- [ ] **Step 2: Update model transaksi**

Untuk `DailyMonitoring.php`, `NutrientAddition.php`, `PhDownLog.php`, tambahkan di `$fillable` nilai `'staff_id'` dan tambahkan import `use App\Models\Farm\Staff;` (atau namespace yang benar), plus dua method:

```php
public function staff(): BelongsTo
{
    return $this->belongsTo(Staff::class);
}

public function actorName(): ?string
{
    return $this->user?->name ?? $this->staff?->name ?? null;
}
```

Untuk `ActivityLog.php`: tambahkan `'staff_id'` ke `$fillable` + method `staff(): BelongsTo` (tanpa `actorName`).

- [ ] **Step 3: Tulis test gagal**

`tests/Feature/Staff/StaffAttributionTest.php`:

```php
<?php

namespace Tests\Feature\Staff;

use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffAttributionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_monitoring_actor_name_returns_staff_name(): void
    {
        $staff = Staff::factory()->create();
        $tank = Tank::factory()->create(['farm_id' => $staff->farm_id]);
        $monitoring = DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'staff_id' => $staff->id,
            'user_id' => null,
        ]);

        $this->assertSame($staff->name, $monitoring->actorName());
    }

    public function test_nutrient_actor_name_returns_user_name(): void
    {
        $user = User::factory()->create();
        $tank = Tank::factory()->create();
        $addition = NutrientAddition::factory()->create([
            'tank_id' => $tank->id,
            'user_id' => $user->id,
            'staff_id' => null,
        ]);

        $this->assertSame($user->name, $addition->actorName());
    }

    public function test_ph_down_log_staff_relation(): void
    {
        $staff = Staff::factory()->create();
        $tank = Tank::factory()->create(['farm_id' => $staff->farm_id]);
        $log = PhDownLog::factory()->create([
            'tank_id' => $tank->id,
            'staff_id' => $staff->id,
            'user_id' => null,
        ]);

        $this->assertTrue($log->staff->is($staff));
    }
}
```

Periksa apakah `Tank::factory()` dan factory `DailyMonitoring`/`NutrientAddition`/`PhDownLog` sudah ada dan kolom `user_id`-nya diisi. Jika factory belum mendukung, buat instance langsung dengan array (lihat `database/factories/`). Pastikan factory `DailyMonitoringFactory` dsb. berisi `user_id => User::factory()` — set `user_id => null` secara eksplisit di test di atas.

- [ ] **Step 4: Jalankan test, pastikan gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Staff/StaffAttributionTest.php`
Expected: FAIL (kolom `staff_id` belum ada / method tidak ada).

- [ ] **Step 5: Jalankan migration + update model**

Run: `vendor/bin/sail artisan migrate`
Terapkan perubahan model (Step 2). Jalankan `vendor/bin/sail bin pint --format agent`.

- [ ] **Step 6: Update tampilan atribusi**

Ganti baris yang menampilkan nama pelaku dari `$m->user->name` menjadi `$m->actorName()` di:
- `resources/views/daily-monitoring/index.blade.php` (baris `{{ $m->user->name ?? '—' }}`)
- `resources/views/nutrient-addition/index.blade.php` (cari `$a->user` atau sejenis)
- `resources/views/ph-down-log/index.blade.php` (sama)
- `resources/views/tank/show.blade.php` (bagian monitoring/nutrient/ph — ganti `user->name` dengan `actorName()`)
- `resources/views/activity-logs/index.blade.php` (cari kolom yang menampilkan nama user — ganti menjadi `$log->user?->name ?? $log->staff?->name ?? '—'`)

Untuk men-detect, jalankan: `rg -n "user->name|->user\b" resources/views/{daily-monitoring,nutrient-addition,ph-down-log,tank,activity-logs}`

- [ ] **Step 7: Jalankan test, pastikan pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Staff/StaffAttributionTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_03_000001_add_staff_id_to_transaction_tables.php app/Models/Farm/DailyMonitoring.php app/Models/Farm/NutrientAddition.php app/Models/Farm/PhDownLog.php app/Models/Farm/ActivityLog.php resources/views/daily-monitoring/index.blade.php resources/views/nutrient-addition/index.blade.php resources/views/ph-down-log/index.blade.php resources/views/tank/show.blade.php resources/views/activity-logs/index.blade.php tests/Feature/Staff/StaffAttributionTest.php
git commit -m "feat: atribusi staff pada catatan transaksi"
```

---

### Task 3: Unique `farms.name` + Migrasi Role `member` → `manager`

**Files:**
- Create: `database/migrations/2026_08_03_000002_make_farms_name_unique.php`
- Create: `database/migrations/2026_08_03_000003_migrate_farm_member_role_to_manager.php`
- Test: `tests/Feature/Farm/RoleMigrationTest.php`

**Interfaces:**
- Produces: index unik pada `farms.name`; semua baris `farm_users` role `member` menjadi `manager`.

- [ ] **Step 1: Tulis migration unique name**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->string('name')->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
```

Catatan: `farms.name` punya soft deletes — baris farm terhapus tetap mereservasi nama. Sesuai keputusan desain.

- [ ] **Step 2: Tulis migration role**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('farm_users')->where('role', 'member')->update(['role' => 'manager']);
    }

    public function down(): void
    {
        // Tidak ada rollback data — role baru sudah dipakai setelah deploy.
    }
};
```

- [ ] **Step 3: Tulis test gagal**

`tests/Feature/Farm/RoleMigrationTest.php`:

```php
<?php

namespace Tests\Feature\Farm;

use App\Models\Farm;
use App\Models\Farm\FarmUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_farm_name_unique_index_enforced(): void
    {
        Farm::factory()->create(['name' => 'Kebun Satu']);

        $this->expectException(\Illuminate\Database\QueryException::class);
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
```

- [ ] **Step 4: Jalankan test, pastikan gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Farm/RoleMigrationTest.php`
Expected: FAIL (unique belum ada / update role belum diverifikasi).

- [ ] **Step 5: Jalankan migration**

Run: `vendor/bin/sail artisan migrate`

- [ ] **Step 6: Jalankan test, pastikan pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Farm/RoleMigrationTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_03_000002_make_farms_name_unique.php database/migrations/2026_08_03_000003_migrate_farm_member_role_to_manager.php tests/Feature/Farm/RoleMigrationTest.php
git commit -m "feat: unique farms.name & migrasi role member ke manager"
```

---

### Task 4: `FarmPolicy` — Semantik Owner/Manager

**Files:**
- Modify: `app/Policies/FarmPolicy.php`
- Test: `tests/Feature/Farm/FarmPolicyTest.php`

**Interfaces:**
- Produces: `FarmPolicy::update()` → owner|manager; `delete()` → owner; `manageMembers()` → owner|manager; `manageStaff()` → owner|manager; `transferOwnership()` → owner.

- [ ] **Step 1: Tulis test gagal**

`tests/Feature/Farm/FarmPolicyTest.php`:

```php
<?php

namespace Tests\Feature\Farm;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class FarmPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function farmWithOwner(): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);

        return compact('owner', 'farm');
    }

    public function test_manager_can_update_farm(): void
    {
        ['farm' => $farm] = $this->farmWithOwner();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $this->assertTrue(Gate::forUser($manager)->allows('update', $farm));
        $this->assertTrue(Gate::forUser($manager)->allows('manageStaff', $farm));
        $this->assertTrue(Gate::forUser($manager)->allows('manageMembers', $farm));
    }

    public function test_manager_cannot_delete_or_transfer(): void
    {
        ['farm' => $farm] = $this->farmWithOwner();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $this->assertTrue(Gate::forUser($manager)->denies('delete', $farm));
        $this->assertTrue(Gate::forUser($manager)->denies('transferOwnership', $farm));
    }

    public function test_owner_can_do_everything(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->farmWithOwner();

        $this->assertTrue(Gate::forUser($owner)->allows('update', $farm));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $farm));
        $this->assertTrue(Gate::forUser($owner)->allows('transferOwnership', $farm));
        $this->assertTrue(Gate::forUser($owner)->allows('manageMembers', $farm));
    }

    public function test_unrelated_user_cannot_view_farm(): void
    {
        ['farm' => $farm] = $this->farmWithOwner();
        $stranger = User::factory()->create();

        $this->assertTrue(Gate::forUser($stranger)->denies('view', $farm));
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Farm/FarmPolicyTest.php`
Expected: FAIL (method `manageStaff`, `manageMembers`, `transferOwnership` belum ada).

- [ ] **Step 3: Update `FarmPolicy.php`**

Ganti isi method `update` dan `delete`, tambahkan method baru:

```php
public function update(User $user, Farm $farm): bool
{
    return $farm->users()
        ->where('user_id', $user->id)
        ->wherePivotIn('role', ['owner', 'manager'])
        ->exists();
}

public function delete(User $user, Farm $farm): bool
{
    return $farm->users()
        ->where('user_id', $user->id)
        ->wherePivot('role', 'owner')
        ->exists();
}

public function manageMembers(User $user, Farm $farm): bool
{
    return $this->update($user, $farm);
}

public function manageStaff(User $user, Farm $farm): bool
{
    return $this->update($user, $farm);
}

public function transferOwnership(User $user, Farm $farm): bool
{
    return $this->delete($user, $farm);
}
```

- [ ] **Step 4: Jalankan test, pastikan pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Farm/FarmPolicyTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Policies/FarmPolicy.php tests/Feature/Farm/FarmPolicyTest.php
git commit -m "feat: FarmPolicy owner/manager semantics"
```

---

### Task 5: Otorisasi `FarmController` + Transfer Kepemilikan

**Files:**
- Modify: `app/Http/Controllers/Farm/FarmController.php`
- Modify: `routes/farm.php`
- Modify: `resources/views/farm/show.blade.php`
- Test: `tests/Feature/Farm/FarmAuthorizationTest.php`

**Interfaces:**
- Consumes: `FarmPolicy::update`, `FarmPolicy::transferOwnership` dari Task 4.
- Produces: route `POST /farm/{farm}/transfer` → `farm.transfer`; method `FarmController::transferOwnership(Request, Farm)`.

- [ ] **Step 1: Tulis test gagal**

`tests/Feature/Farm/FarmAuthorizationTest.php`:

```php
<?php

namespace Tests\Feature\Farm;

use App\Models\Farm;
use App\Models\Farm\FarmUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FarmAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setUpFarm(): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        session()->put('selected_farm_id', $farm->id);

        return compact('owner', 'farm');
    }

    public function test_manager_can_update_farm(): void
    {
        ['farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($manager)->put(route('farm.update', $farm), [
            'name' => 'Nama Baru',
            'address' => 'Alamat Baru',
            'description' => 'Deskripsi Baru',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('farms', ['id' => $farm->id, 'name' => 'Nama Baru']);
    }

    public function test_manager_cannot_delete_farm(): void
    {
        ['farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($manager)->delete(route('farm.destroy', $farm));

        $response->assertForbidden();
    }

    public function test_owner_can_transfer_ownership_to_manager(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($owner)->post(route('farm.transfer', $farm), [
            'new_owner_id' => $manager->id,
        ]);

        $response->assertRedirect();
        $this->assertSame('owner', FarmUser::where('farm_id', $farm->id)->where('user_id', $manager->id)->first()->role);
        $this->assertSame('manager', FarmUser::where('farm_id', $farm->id)->where('user_id', $owner->id)->first()->role);
    }

    public function test_manager_cannot_transfer_ownership(): void
    {
        ['farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($manager)->post(route('farm.transfer', $farm), [
            'new_owner_id' => $manager->id,
        ]);

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Farm/FarmAuthorizationTest.php`
Expected: FAIL (route `farm.transfer` belum ada; `update` mungkin belum diotorisasi).

- [ ] **Step 3: Tambah otorisasi update di `FarmController`**

Di method `update`, tambahkan di awal: `Gate::authorize('update', $farm);`

- [ ] **Step 4: Tambah method transfer + route**

Di `FarmController`:

```php
public function transferOwnership(Request $request, Farm $farm): RedirectResponse
{
    Gate::authorize('transferOwnership', $farm);

    $validated = $request->validate([
        'new_owner_id' => ['required', 'exists:users,id'],
    ]);

    $newOwner = $farm->users()->findOrFail($validated['new_owner_id']);

    DB::transaction(function () use ($farm, $newOwner, $request) {
        $farm->users()->updateExistingPivot($newOwner->id, ['role' => 'owner']);
        $farm->users()->updateExistingPivot($request->user()->id, ['role' => 'manager']);
    });

    return redirect()->route('farm.show', $farm)
        ->with('success', 'Kepemilikan kebun berhasil ditransfer.');
}
```

Tambahkan import `use Illuminate\Support\Facades\DB;` dan `use Illuminate\Http\Request;` di `FarmController`.

Di `routes/farm.php`, di dalam grup `farm` (setelah route destroy):

```php
Route::post('/{farm}/transfer', [FarmController::class, 'transferOwnership'])->name('transfer');
```

- [ ] **Step 5: Tambah UI transfer di `farm/show.blade.php`**

Di dalam area yang terlihat oleh owner, tambahkan blok (tempatkan di bawah daftar member/tank):

```blade
@can('transferOwnership', $farm)
    <div class="mt-8 rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5">
        <h3 class="text-lg font-semibold text-slate-900">Transfer Kepemilikan</h3>
        <p class="mt-1 text-sm text-slate-500">Serahkan kepemilikan kebun ke anggota lain. Anda akan menjadi manager.</p>
        <form action="{{ route('farm.transfer', $farm) }}" method="POST" class="mt-4 flex flex-wrap items-end gap-4"
            onsubmit="return confirm('Yakin ingin mentransfer kepemilikan kebun? Anda akan menjadi manager.')">
            @csrf
            <div class="flex-1 min-w-[220px]">
                <label for="new_owner_id" class="block text-sm font-semibold text-slate-700">Anggota Baru</label>
                <select name="new_owner_id" id="new_owner_id" required
                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                    @foreach($farm->users as $user)
                        @if($user->id !== auth()->id())
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ ucfirst($user->pivot->role) }})</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <button type="submit"
                class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-6 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                <i class="bi bi-arrow-left-right"></i>
                Transfer
            </button>
        </form>
    </div>
@endcan
```

Periksa struktur `farm/show.blade.php` sebelum menambahkan — sesuaikan posisi agar konsisten dengan markup existing (mis. berada dalam wrapper yang sama).

- [ ] **Step 6: Jalankan test, pastikan pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Farm/FarmAuthorizationTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Farm/FarmController.php routes/farm.php resources/views/farm/show.blade.php tests/Feature/Farm/FarmAuthorizationTest.php
git commit -m "feat: otorisasi farm update & transfer kepemilikan"
```

---

### Task 6: `FarmUserController` — Role Manager + Proteksi Owner

**Files:**
- Modify: `app/Http/Controllers/FarmUserController.php`
- Modify: `app/Http/Requests/StoreFarmUserRequest.php`
- Modify: `resources/views/farm-members/index.blade.php`
- Modify: `resources/views/farm-members/create.blade.php`
- Modify: `tests/Feature/FarmMember/FarmMemberTest.php`
- Test: `tests/Feature/FarmMember/FarmMemberRoleTest.php`

**Interfaces:**
- Consumes: `FarmPolicy::update` (owner|manager) dari Task 4.
- Produces: `FarmUserController::store` membuat role `manager`; `destroy` menolak menghapus pivot ber-role `owner`; `StoreFarmUserRequest` diotorisasi owner|manager.

- [ ] **Step 1: Tulis test gagal**

`tests/Feature/FarmMember/FarmMemberRoleTest.php`:

```php
<?php

namespace Tests\Feature\FarmMember;

use App\Models\Farm;
use App\Models\Farm\FarmUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FarmMemberRoleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setUpFarm(): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        session()->put('selected_farm_id', $farm->id);

        return compact('owner', 'farm');
    }

    public function test_invited_member_gets_manager_role(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $invitee = User::factory()->create();

        $response = $this->actingAs($owner)->post(route('farm.members.store', $farm), [
            'email' => $invitee->name,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('farm_users', [
            'farm_id' => $farm->id,
            'user_id' => $invitee->id,
            'role' => 'manager',
        ]);
    }

    public function test_manager_can_invite_member(): void
    {
        ['farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);
        $invitee = User::factory()->create();

        $response = $this->actingAs($manager)->post(route('farm.members.store', $farm), [
            'email' => $invitee->name,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('farm_users', [
            'farm_id' => $farm->id,
            'user_id' => $invitee->id,
            'role' => 'manager',
        ]);
    }

    public function test_manager_cannot_remove_owner(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);
        $ownerFarmUser = FarmUser::where('farm_id', $farm->id)->where('user_id', $owner->id)->first();

        $response = $this->actingAs($manager)->delete(route('farm.members.destroy', [$farm, $ownerFarmUser]));

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('farm_users', ['farm_id' => $farm->id, 'user_id' => $owner->id]);
    }

    public function test_owner_can_remove_manager(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);
        $managerFarmUser = FarmUser::where('farm_id', $farm->id)->where('user_id', $manager->id)->first();

        $response = $this->actingAs($owner)->delete(route('farm.members.destroy', [$farm, $managerFarmUser]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('farm_users', [
            'farm_id' => $farm->id,
            'user_id' => $manager->id,
        ]);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/FarmMember/FarmMemberRoleTest.php`
Expected: FAIL (store masih role `member`; destroy belum proteksi owner).

- [ ] **Step 3: Update `StoreFarmUserRequest`**

Ganti method `authorize`:

```php
public function authorize(): bool
{
    /** @var Farm $farm */
    $farm = $this->route('farm');

    return $farm->users()
        ->where('user_id', $this->user()->id)
        ->wherePivotIn('role', ['owner', 'manager'])
        ->exists();
}
```

- [ ] **Step 4: Update `FarmUserController`**

`store`: ganti `['role' => 'member']` → `['role' => 'manager']`.

`destroy`: ganti otorisasi menjadi `Gate::authorize('manageMembers', $farm)` dan tambahkan proteksi owner:

```php
public function destroy(Request $request, Farm $farm, FarmUser $farmUser): RedirectResponse
{
    Gate::authorize('manageMembers', $farm);

    if ($farmUser->role === 'owner') {
        return back()->withErrors(['error' => 'Pemilik kebun tidak dapat dihapus.']);
    }

    if ($farmUser->user_id === $request->user()->id) {
        return back()->withErrors(['error' => 'Anda tidak dapat menghapus diri sendiri.']);
    }

    $farmUser->delete();

    return redirect()->route('farm.members.index', $farm)
        ->with('success', 'Anggota berhasil dihapus.');
}
```

Tambahkan `use Illuminate\Support\Facades\Gate;` jika belum ada.

- [ ] **Step 5: Update view**

`resources/views/farm-members/index.blade.php`:
- Ubah badge role: warna amber untuk `owner`, biru untuk `manager` (baris `$user->pivot->role === 'owner' ? ... : ...` sudah pas, cukup pastikan label `ucfirst` menampilkan "Manager").
- Button "Undang Anggota" — ganti `@can('update', $farm)` tetap (owner|manager, sudah benar).

`resources/views/farm-members/create.blade.php`:
- Teks label tetap "Email User" (lookup by `users.name` legacy), tapi tambahkan catatan kecil bahwa user akan bergabung sebagai **Manager**.

- [ ] **Step 6: Perbarui test existing yang menggunakan role `member`**

Di `tests/Feature/FarmMember/FarmMemberTest.php`:
- `test_owner_can_invite_member_by_email`: ubah assert `'role' => 'member'` → `'role' => 'manager'`.
- `test_owner_can_remove_member`: tidak perlu diubah (role tidak dicek di destroy).
- `test_member_cannot_invite`: ganti `$farm->users()->attach($member->id, ['role' => 'member'])` → tetap valid (role member tidak bisa invite karena hanya owner|manager yang bisa); biarkan.

- [ ] **Step 7: Jalankan seluruh test farm member**

Run: `vendor/bin/sail artisan test --compact tests/Feature/FarmMember`
Expected: PASS (semua).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/FarmUserController.php app/Http/Requests/StoreFarmUserRequest.php resources/views/farm-members/index.blade.php resources/views/farm-members/create.blade.php tests/Feature/FarmMember/FarmMemberTest.php tests/Feature/FarmMember/FarmMemberRoleTest.php
git commit -m "feat: role manager pada member & proteksi owner"
```

---

### Task 7: Login Staff (Auth Terpisah)

**Files:**
- Create: `app/Http/Controllers/Staff/StaffAuthController.php`
- Create: `app/Http/Requests/Staff/StaffLoginRequest.php`
- Create: `routes/staff.php`
- Modify: `routes/web.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Create: `resources/views/staff/auth/login.blade.php`
- Test: `tests/Feature/StaffAuth/StaffAuthTest.php`

**Interfaces:**
- Consumes: guard `staff`, model `Staff` dari Task 1.
- Produces: routes `staff.login` (GET), `staff.login.attempt` (POST, throttle:staff-login), `staff.logout` (POST); controller `StaffAuthController::showLoginForm/login/logout`.

- [ ] **Step 1: Tulis request**

`app/Http/Requests/Staff/StaffLoginRequest.php`:

```php
<?php

namespace App\Http\Requests\Staff;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StaffLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'farm_name' => ['required', 'string'],
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'farm_name.required' => 'Nama kebun wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ];
    }
}
```

- [ ] **Step 2: Tulis controller**

`app/Http/Controllers/Staff/StaffAuthController.php`:

```php
<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StaffLoginRequest;
use App\Models\Farm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class StaffAuthController extends Controller
{
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::guard('staff')->check()) {
            return redirect()->route('staff.dashboard');
        }

        if (Auth::guard('web')->check()) {
            return redirect()->route('dashboard');
        }

        return view('staff.auth.login');
    }

    public function login(StaffLoginRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $farm = Farm::where('name', $validated['farm_name'])->first();
        $staff = $farm?->staff()->where('username', $validated['username'])->first();

        if (! $staff || ! Hash::check($validated['password'], $staff->password)) {
            return back()
                ->withInput($request->only('farm_name', 'username'))
                ->withErrors(['farm_name' => 'Nama kebun, username, atau password salah.']);
        }

        if (! $staff->is_active) {
            return back()
                ->withInput($request->only('farm_name', 'username'))
                ->withErrors(['farm_name' => 'Akun tidak aktif. Hubungi pemilik kebun.']);
        }

        Auth::guard('staff')->login($staff);
        $request->session()->regenerate();

        return redirect()->route('staff.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('staff.login');
    }
}
```

- [ ] **Step 3: Tulis routes**

`routes/staff.php`:

```php
<?php

use App\Http\Controllers\Staff\StaffAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/staff/login', [StaffAuthController::class, 'showLoginForm'])->name('staff.login');
Route::post('/staff/login', [StaffAuthController::class, 'login'])
    ->middleware('throttle:staff-login')
    ->name('staff.login.attempt');
Route::post('/staff/logout', [StaffAuthController::class, 'logout'])
    ->middleware('auth:staff')
    ->name('staff.logout');
```

Di `routes/web.php`, tambahkan di baris terakhir sebelum require pwa/profile:

```php
require __DIR__.'/staff.php';
```

- [ ] **Step 4: Tambah rate limiter staff-login di `AppServiceProvider`**

Di `boot()`, setelah `RateLimiter::for('login', ...)`:

```php
RateLimiter::for('staff-login', function (Request $request) {
    return [
        Limit::perMinute(5)->by($request->input('username').'|'.$request->ip()),
        Limit::perMinute(10)->by($request->ip()),
    ];
});
```

- [ ] **Step 5: Tulis view login staff**

`resources/views/staff/auth/login.blade.php` (extends `layouts.auth`, mengikuti pola `auth/login.blade.php` — lihat file tersebut untuk gaya form):

```blade
@extends('layouts.auth')

@section('title', 'Login Petugas Lapangan')

@section('content')
    <div class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <div class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[#ffce54] text-[#1a1c1e]">
                    <i class="bi bi-droplet-half text-xl"></i>
                </div>
                <h1 class="mt-4 text-2xl font-bold">Login Petugas Lapangan</h1>
                <p class="mt-1 text-sm text-white/60">Masuk untuk mencatat data di kebun yang ditugaskan.</p>
            </div>

            <div class="rounded-[2rem] border border-white/10 bg-white/5 p-6 backdrop-blur">
                @if($errors->any())
                    <div class="mb-4 rounded-xl border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form action="{{ route('staff.login.attempt') }}" method="POST" class="space-y-5">
                    @csrf

                    <div>
                        <label for="farm_name" class="block text-sm font-semibold text-white/80">Nama Kebun</label>
                        <input type="text" name="farm_name" id="farm_name" value="{{ old('farm_name') }}" required autofocus
                            class="mt-1.5 block w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm text-white placeholder-white/40 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                    </div>

                    <div>
                        <label for="username" class="block text-sm font-semibold text-white/80">Username</label>
                        <input type="text" name="username" id="username" value="{{ old('username') }}" required
                            class="mt-1.5 block w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm text-white placeholder-white/40 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-white/80">Password</label>
                        <input type="password" name="password" id="password" required
                            class="mt-1.5 block w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm text-white placeholder-white/40 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                    </div>

                    <button type="submit"
                        class="w-full rounded-2xl bg-[#ffce54] px-6 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                        Masuk
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-white/50">
                    Bukan petugas? <a href="{{ route('login') }}" class="font-semibold text-[#ffce54] hover:underline">Login User</a>
                </p>
            </div>
        </div>
    </div>
@endsection
```

Periksa `resources/views/auth/login.blade.php` untuk konsistensi markup (input class dsb.) — sesuaikan bila perlu.

- [ ] **Step 6: Tulis test**

`tests/Feature/StaffAuth/StaffAuthTest.php`:

```php
<?php

namespace Tests\Feature\StaffAuth;

use App\Models\Farm;
use App\Models\Farm\Staff;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffAuthTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_can_login(): void
    {
        $farm = Farm::factory()->create(['name' => 'Kebun A']);
        $staff = Staff::factory()->create(['farm_id' => $farm->id, 'username' => 'anton', 'password' => 'password']);

        $response = $this->post(route('staff.login.attempt'), [
            'farm_name' => 'Kebun A',
            'username' => 'anton',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('staff.dashboard'));
        $this->assertAuthenticatedAs($staff, 'staff');
    }

    public function test_staff_login_with_wrong_password_fails(): void
    {
        $farm = Farm::factory()->create(['name' => 'Kebun A']);
        Staff::factory()->create(['farm_id' => $farm->id, 'username' => 'anton', 'password' => 'password']);

        $response = $this->post(route('staff.login.attempt'), [
            'farm_name' => 'Kebun A',
            'username' => 'anton',
            'password' => 'salah',
        ]);

        $response->assertSessionHasErrors('farm_name');
        $this->assertGuest('staff');
    }

    public function test_staff_login_with_unknown_farm_fails(): void
    {
        Farm::factory()->create(['name' => 'Kebun A']);
        Staff::factory()->create(['farm_id' => 1, 'username' => 'anton', 'password' => 'password']);

        $response = $this->post(route('staff.login.attempt'), [
            'farm_name' => 'Kebun Tidak Ada',
            'username' => 'anton',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('farm_name');
    }

    public function test_inactive_staff_cannot_login(): void
    {
        $farm = Farm::factory()->create(['name' => 'Kebun A']);
        Staff::factory()->create(['farm_id' => $farm->id, 'username' => 'anton', 'password' => 'password', 'is_active' => false]);

        $response = $this->post(route('staff.login.attempt'), [
            'farm_name' => 'Kebun A',
            'username' => 'anton',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('farm_name');
    }

    public function test_staff_can_logout(): void
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $response = $this->actingAs($staff, 'staff')->post(route('staff.logout'));

        $response->assertRedirect(route('staff.login'));
        $this->assertGuest('staff');
    }

    public function test_staff_cannot_access_user_dashboard(): void
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $response = $this->actingAs($staff, 'staff')->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }
}
```

Catatan: `test_staff_login_with_unknown_farm_fails` menggunakan `Staff::factory()->create(['farm_id' => 1, ...])` — pastikan farm pertama dibuat dengan id 1 (dalam test yang terisolasi dengan DB fresh, iya). Lebih aman: gunakan farm yang dibuat: `['farm_id' => $farm->id]` dan farm_name yang salah.

- [ ] **Step 7: Jalankan test, pastikan gagal dulu**

Run: `vendor/bin/sail artisan test --compact tests/Feature/StaffAuth/StaffAuthTest.php`
Expected: FAIL (route `staff.login` belum ada).

- [ ] **Step 8: Jalankan implementasi + test pass**

Buat semua file di langkah 1–5. Jalankan `vendor/bin/sail bin pint --format agent`.
Run: `vendor/bin/sail artisan test --compact tests/Feature/StaffAuth/StaffAuthTest.php`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Staff/StaffAuthController.php app/Http/Requests/Staff/StaffLoginRequest.php routes/staff.php routes/web.php app/Providers/AppServiceProvider.php resources/views/staff/auth/login.blade.php tests/Feature/StaffAuth/StaffAuthTest.php
git commit -m "feat: login petugas lapangan (guard staff)"
```

---

### Task 8: Layout Staff + Dashboard

**Files:**
- Create: `resources/views/layouts/staff.blade.php`
- Create: `app/Http/Controllers/Staff/StaffDashboardController.php`
- Create: `resources/views/staff/dashboard/index.blade.php`
- Modify: `routes/staff.php`
- Test: `tests/Feature/Staff/StaffDashboardTest.php`

**Interfaces:**
- Consumes: guard `staff`; route `staff.dashboard` (GET `/staff`) dari Task 7.
- Produces: layout `layouts.staff` (header dengan nama kebun + nav + `@yield('content')`); `StaffDashboardController::index(): View`.

- [ ] **Step 1: Tulis layout staff**

`resources/views/layouts/staff.blade.php`:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - Petugas Lapangan</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f8f6f2] text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-[#ffce54] text-[#1a1c1e]">
                    <i class="bi bi-droplet-half"></i>
                </span>
                <div>
                    <p class="text-sm font-semibold text-slate-900">{{ auth('staff')->user()->farm->name }}</p>
                    <p class="text-xs text-slate-500">{{ auth('staff')->user()->name }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('staff.logout') }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-red-50 hover:text-red-600">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </button>
            </form>
        </div>
    </header>

    <nav class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl gap-1 overflow-x-auto px-4">
            @php
                $navs = [
                    ['label' => 'Dashboard', 'route' => 'staff.dashboard'],
                    ['label' => 'Monitoring', 'route' => 'staff.monitoring.create'],
                    ['label' => 'AB Mix', 'route' => 'staff.nutrient.create'],
                    ['label' => 'pH Down', 'route' => 'staff.ph-down.create'],
                    ['label' => 'Catatan Saya', 'route' => 'staff.monitoring.index'],
                    ['label' => 'Laporan', 'route' => 'staff.reports.monitoring'],
                ];
            @endphp
            @foreach($navs as $nav)
                <a href="{{ route($nav['route']) }}"
                    class="whitespace-nowrap px-4 py-3 text-sm font-semibold text-slate-600 transition hover:text-[#1a1c1e] {{ request()->routeIs(str_replace('.create', '.*', $nav['route']), $nav['route']) ? 'border-b-2 border-[#ffce54] text-[#1a1c1e]' : '' }}">
                    {{ $nav['label'] }}
                </a>
            @endforeach
        </div>
    </nav>

    <main class="mx-auto max-w-5xl px-4 py-6">
        @yield('content')
    </main>
</body>
</html>
```

Catatan: logika active-nav sederhana — gunakan `request()->routeIs('staff.*')` dengan segment per tipe. Untuk kesederhanaan, beri class aktif jika `request()->routeIs($nav['route'])` atau route mulai dengan prefix yang sama.

- [ ] **Step 2: Tulis controller dashboard**

`app/Http/Controllers/Staff/StaffDashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class StaffDashboardController extends Controller
{
    public function index(): View
    {
        $farm = auth('staff')->user()->farm->load('tanks');

        $tanks = $farm->tanks;
        $stats = [
            'total_tanks' => $tanks->count(),
            'active_tanks' => $tanks->where('is_active', true)->count(),
            'avg_ppm' => $tanks->avg('current_ppm') ? round($tanks->avg('current_ppm'), 1) : null,
            'avg_ph' => $tanks->avg('current_ph') ? round($tanks->avg('current_ph'), 1) : null,
            'avg_temp' => $tanks->avg('current_water_temperature') ? round($tanks->avg('current_water_temperature'), 1) : null,
        ];

        return view('staff.dashboard.index', compact('farm', 'stats'));
    }
}
```

- [ ] **Step 3: Tulis view dashboard**

`resources/views/staff/dashboard/index.blade.php`:

```blade
@extends('layouts.staff')

@section('title', 'Dashboard')

@section('content')
    <div>
        <h2 class="text-lg font-semibold text-slate-900">Dashboard {{ $farm->name }}</h2>
        <p class="mt-1 text-sm text-slate-500">Ringkasan kondisi kebun yang ditugaskan.</p>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Tank</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $stats['total_tanks'] }}</p>
        </div>
        <div class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tank Aktif</p>
            <p class="mt-2 text-2xl font-bold text-emerald-600">{{ $stats['active_tanks'] }}</p>
        </div>
        <div class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Rata-rata PPM</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $stats['avg_ppm'] ?? '—' }}</p>
        </div>
        <div class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Rata-rata pH</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $stats['avg_ph'] ?? '—' }}</p>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <a href="{{ route('staff.monitoring.create') }}"
            class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5 transition hover:bg-[#ffce54]/5">
            <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-[#4fc3f7]/15 text-[#4fc3f7]">
                <i class="bi bi-thermometer-half"></i>
            </div>
            <h3 class="mt-4 text-sm font-semibold text-slate-900">Input Monitoring</h3>
            <p class="mt-1 text-xs text-slate-500">Catat PPM, pH, dan suhu harian.</p>
        </a>
        <a href="{{ route('staff.nutrient.create') }}"
            class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5 transition hover:bg-[#ffce54]/5">
            <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-600">
                <i class="bi bi-droplet"></i>
            </div>
            <h3 class="mt-4 text-sm font-semibold text-slate-900">Input AB Mix</h3>
            <p class="mt-1 text-xs text-slate-500">Catat penambahan nutrisi AB Mix.</p>
        </a>
        <a href="{{ route('staff.ph-down.create') }}"
            class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5 transition hover:bg-[#ffce54]/5">
            <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-500/15 text-amber-600">
                <i class="bi bi-flask"></i>
            </div>
            <h3 class="mt-4 text-sm font-semibold text-slate-900">Input pH Down</h3>
            <p class="mt-1 text-xs text-slate-500">Catat penurunan pH.</p>
        </a>
    </div>
@endsection
```

- [ ] **Step 4: Tambah route dashboard**

Di `routes/staff.php` (grup dengan `auth:staff` — buat grup ini sekarang, Task 9–11 akan menambahkan route lain):

```php
Route::middleware('auth:staff')->group(function () {
    Route::get('/staff', [StaffDashboardController::class, 'index'])->name('staff.dashboard');
});
```

Tambahkan `use App\Http\Controllers\Staff\StaffDashboardController;` di `routes/staff.php`.

- [ ] **Step 5: Tulis test**

`tests/Feature/Staff/StaffDashboardTest.php`:

```php
<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\Staff;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_can_view_dashboard(): void
    {
        $farm = Farm::factory()->create(['name' => 'Kebun A']);
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $response = $this->actingAs($staff, 'staff')->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('Kebun A');
    }

    public function test_guest_cannot_access_staff_dashboard(): void
    {
        $response = $this->get(route('staff.dashboard'));

        $response->assertRedirect(route('staff.login'));
    }
}
```

- [ ] **Step 6: Jalankan test, pastikan gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Staff/StaffDashboardTest.php`
Expected: FAIL (route `staff.dashboard` belum ada).

- [ ] **Step 7: Implementasi + test pass**

Buat file langkah 1–4. Run: `vendor/bin/sail artisan test --compact tests/Feature/Staff/StaffDashboardTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/views/layouts/staff.blade.php app/Http/Controllers/Staff/StaffDashboardController.php resources/views/staff/dashboard/index.blade.php routes/staff.php tests/Feature/Staff/StaffDashboardTest.php
git commit -m "feat: layout & dashboard petugas lapangan"
```

---

### Task 9: Transaksi Staff — Daily Monitoring

**Files:**
- Create: `app/Http/Controllers/Staff/StaffMonitoringController.php`
- Create: `resources/views/staff/monitoring/create.blade.php`
- Create: `resources/views/staff/monitoring/index.blade.php`
- Create: `resources/views/staff/monitoring/edit.blade.php`
- Modify: `routes/staff.php`
- Test: `tests/Feature/Staff/StaffMonitoringTest.php`

**Interfaces:**
- Consumes: `DailyMonitoring` (kolom `staff_id`, `actorName()`), guard `staff`, layout `layouts.staff` dari Task 2/8.
- Produces: routes `staff.monitoring.index/create/store/edit/update/destroy`; `StaffMonitoringController` dengan cek kepemilikan (`staff_id === auth('staff')->id()`) dan scope farm.

- [ ] **Step 1: Tulis controller**

`app/Http/Controllers/Staff/StaffMonitoringController.php`:

```php
<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Tank;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StaffMonitoringController extends Controller
{
    private function staff(): \App\Models\Farm\Staff
    {
        return auth('staff')->user();
    }

    private function farmTanks()
    {
        return Tank::where('farm_id', $this->staff()->farm_id)->orderBy('name')->get();
    }

    public function index(): View
    {
        $monitorings = DailyMonitoring::where('staff_id', $this->staff()->id)
            ->with('tank')
            ->latest('log_date')
            ->paginate(20);

        return view('staff.monitoring.index', compact('monitorings'));
    }

    public function create(): View
    {
        return view('staff.monitoring.create', ['tanks' => $this->farmTanks()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ppm' => 'required|numeric|min:0|max:3000',
            'ph' => 'required|numeric|min:0|max:14',
            'water_temperature' => 'nullable|numeric|min:-10|max:60',
            'notes' => 'nullable|string|max:1000',
        ]);

        $tank = Tank::where('id', $validated['tank_id'])
            ->where('farm_id', $this->staff()->farm_id)
            ->first();

        if (! $tank) {
            abort(403);
        }

        $exists = DailyMonitoring::where('tank_id', $validated['tank_id'])
            ->where('log_date', $validated['log_date'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['log_date' => 'Monitoring untuk tank ini pada tanggal tersebut sudah ada.'])->withInput();
        }

        DailyMonitoring::create($validated + [
            'staff_id' => $this->staff()->id,
            'user_id' => null,
        ]);

        return redirect()->route('staff.monitoring.index')
            ->with('success', 'Data monitoring berhasil disimpan.');
    }

    public function edit(DailyMonitoring $dailyMonitoring): View
    {
        abort_unless($this->owns($dailyMonitoring), 403);

        return view('staff.monitoring.edit', [
            'dailyMonitoring' => $dailyMonitoring,
            'tanks' => $this->farmTanks(),
        ]);
    }

    public function update(Request $request, DailyMonitoring $dailyMonitoring): RedirectResponse
    {
        abort_unless($this->owns($dailyMonitoring), 403);

        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ppm' => 'required|numeric|min:0|max:3000',
            'ph' => 'required|numeric|min:0|max:14',
            'water_temperature' => 'nullable|numeric|min:-10|max:60',
            'notes' => 'nullable|string|max:1000',
        ]);

        $tank = Tank::where('id', $validated['tank_id'])
            ->where('farm_id', $this->staff()->farm_id)
            ->first();

        if (! $tank) {
            abort(403);
        }

        $exists = DailyMonitoring::where('tank_id', $validated['tank_id'])
            ->where('log_date', $validated['log_date'])
            ->where('id', '!=', $dailyMonitoring->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['log_date' => 'Monitoring untuk tank ini pada tanggal tersebut sudah ada.'])->withInput();
        }

        $dailyMonitoring->update($validated);

        return redirect()->route('staff.monitoring.index')
            ->with('success', 'Data monitoring berhasil diperbarui.');
    }

    public function destroy(DailyMonitoring $dailyMonitoring): RedirectResponse
    {
        abort_unless($this->owns($dailyMonitoring), 403);

        $dailyMonitoring->delete();

        return redirect()->route('staff.monitoring.index')
            ->with('success', 'Data monitoring berhasil dihapus.');
    }

    private function owns(DailyMonitoring $dailyMonitoring): bool
    {
        return $dailyMonitoring->staff_id === $this->staff()->id;
    }
}
```

- [ ] **Step 2: Tulis views**

`resources/views/staff/monitoring/create.blade.php` (form serupa `daily-monitoring/create.blade.php` tanpa validasi target-range JS — cukup form dasar):

```blade
@extends('layouts.staff')

@section('title', 'Input Monitoring')

@section('content')
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('staff.dashboard') }}" class="inline-flex items-center gap-2 text-sm text-slate-500 transition hover:text-slate-700">
            <i class="bi bi-arrow-left"></i>
            Kembali
        </a>

        <div class="mt-4 rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5 sm:p-8">
            <h2 class="text-lg font-semibold text-slate-900">Input Daily Monitoring</h2>
            <p class="mt-1 text-sm text-slate-500">Catat PPM, pH, dan suhu air.</p>

            <form action="{{ route('staff.monitoring.store') }}" method="POST" class="mt-6 space-y-5">
                @csrf

                <div>
                    <label for="tank_id" class="block text-sm font-semibold text-slate-700">Tank</label>
                    <select name="tank_id" id="tank_id" required
                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                        <option value="">Pilih tank</option>
                        @foreach($tanks as $tank)
                            <option value="{{ $tank->id }}" @selected(old('tank_id') == $tank->id)>{{ $tank->name }} ({{ number_format($tank->capacity_liter, 0) }} L)</option>
                        @endforeach
                    </select>
                    @error('tank_id')
                        <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="log_date" class="block text-sm font-semibold text-slate-700">Tanggal Monitoring</label>
                    <input type="date" name="log_date" id="log_date" value="{{ old('log_date', date('Y-m-d')) }}" required
                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                    @error('log_date')
                        <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="ppm" class="block text-sm font-semibold text-slate-700">PPM</label>
                        <input type="number" name="ppm" id="ppm" step="0.01" min="0" max="3000" value="{{ old('ppm') }}" required
                            class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20" placeholder="700">
                        @error('ppm')
                            <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="ph" class="block text-sm font-semibold text-slate-700">pH</label>
                        <input type="number" name="ph" id="ph" step="0.01" min="0" max="14" value="{{ old('ph') }}" required
                            class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20" placeholder="6.5">
                        @error('ph')
                            <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="water_temperature" class="block text-sm font-semibold text-slate-700">Suhu Air (°C)</label>
                        <input type="number" name="water_temperature" id="water_temperature" step="0.01" value="{{ old('water_temperature') }}"
                            class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20" placeholder="25">
                        @error('water_temperature')
                            <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-sm font-semibold text-slate-700">Catatan</label>
                    <textarea name="notes" id="notes" rows="3"
                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                        placeholder="Opsional">{{ old('notes') }}</textarea>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-6 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                        <i class="bi bi-floppy"></i>
                        Simpan
                    </button>
                    <a href="{{ route('staff.dashboard') }}"
                        class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
```

`resources/views/staff/monitoring/index.blade.php` (Catatan Saya):

```blade
@extends('layouts.staff')

@section('title', 'Catatan Monitoring Saya')

@section('content')
    <div>
        <h2 class="text-lg font-semibold text-slate-900">Catatan Monitoring Saya</h2>
        <p class="mt-1 text-sm text-slate-500">Riwayat monitoring yang Anda catat.</p>
    </div>

    @if(session('success'))
        <div class="mt-4 rounded-2xl border border-emerald-200/60 bg-emerald-50 px-5 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if($monitorings->isEmpty())
        <div class="mt-8 flex flex-col items-center rounded-[2rem] border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[#4fc3f7]/15 text-[#4fc3f7]">
                <i class="bi bi-clipboard-data"></i>
            </div>
            <h3 class="mt-5 text-lg font-semibold text-slate-900">Belum Ada Catatan</h3>
            <a href="{{ route('staff.monitoring.create') }}" class="mt-5 inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-5 py-2.5 text-sm font-bold text-[#1a1c1e] transition hover:bg-[#f0b830]">
                <i class="bi bi-plus-lg"></i>
                Input Data
            </a>
        </div>
    @else
        <div class="mt-6 overflow-hidden rounded-[2rem] border border-slate-200/60 bg-white shadow-sm shadow-slate-900/5">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-100 bg-slate-50 text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3">Tank</th>
                        <th class="px-5 py-3">PPM</th>
                        <th class="px-5 py-3">pH</th>
                        <th class="px-5 py-3">Suhu</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($monitorings as $m)
                        <tr class="transition hover:bg-slate-50/50">
                            <td class="px-5 py-3 font-medium text-slate-900">{{ $m->log_date ? $m->log_date->format('d M Y') : '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $m->tank->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $m->ppm }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $m->ph }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $m->water_temperature }}°C</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('staff.monitoring.edit', $m) }}"
                                        class="inline-flex items-center gap-1 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                                        <i class="bi bi-pencil"></i>
                                        Edit
                                    </a>
                                    <form action="{{ route('staff.monitoring.destroy', $m) }}" method="POST" onsubmit="return confirm('Hapus catatan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="inline-flex items-center gap-1 rounded-xl border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50">
                                            <i class="bi bi-trash"></i>
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $monitorings->links() }}
        </div>
    @endif
@endsection
```

`resources/views/staff/monitoring/edit.blade.php` — salin `create.blade.php`, ubah action ke `route('staff.monitoring.update', $dailyMonitoring)` dengan `@method('PUT')`, isi nilai input dengan `$dailyMonitoring->...`, dan judul "Edit Daily Monitoring".

- [ ] **Step 3: Tambah routes**

Di `routes/staff.php` di dalam grup `auth:staff` (setelah dashboard):

```php
Route::get('/staff/monitoring', [StaffMonitoringController::class, 'index'])->name('staff.monitoring.index');
Route::get('/staff/monitoring/create', [StaffMonitoringController::class, 'create'])->name('staff.monitoring.create');
Route::post('/staff/monitoring/store', [StaffMonitoringController::class, 'store'])->name('staff.monitoring.store');
Route::get('/staff/monitoring/{dailyMonitoring}/edit', [StaffMonitoringController::class, 'edit'])->name('staff.monitoring.edit');
Route::put('/staff/monitoring/{dailyMonitoring}', [StaffMonitoringController::class, 'update'])->name('staff.monitoring.update');
Route::delete('/staff/monitoring/{dailyMonitoring}', [StaffMonitoringController::class, 'destroy'])->name('staff.monitoring.destroy');
```

- [ ] **Step 4: Tulis test**

`tests/Feature/Staff/StaffMonitoringTest.php`:

```php
<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffMonitoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setUpStaff(): array
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);
        $tank = Tank::factory()->create(['farm_id' => $farm->id]);

        return compact('farm', 'staff', 'tank');
    }

    public function test_staff_can_create_monitoring(): void
    {
        ['staff' => $staff, 'tank' => $tank] = $this->setUpStaff();

        $response = $this->actingAs($staff, 'staff')->post(route('staff.monitoring.store'), [
            'tank_id' => $tank->id,
            'log_date' => '2026-08-03',
            'ppm' => 700,
            'ph' => 6.5,
            'water_temperature' => 25,
        ]);

        $response->assertRedirect(route('staff.monitoring.index'));
        $this->assertDatabaseHas('daily_monitorings', [
            'tank_id' => $tank->id,
            'staff_id' => $staff->id,
            'user_id' => null,
        ]);
    }

    public function test_staff_cannot_use_tank_of_other_farm(): void
    {
        ['staff' => $staff] = $this->setUpStaff();
        $otherFarm = Farm::factory()->create();
        $otherTank = Tank::factory()->create(['farm_id' => $otherFarm->id]);

        $response = $this->actingAs($staff, 'staff')->post(route('staff.monitoring.store'), [
            'tank_id' => $otherTank->id,
            'log_date' => '2026-08-03',
            'ppm' => 700,
            'ph' => 6.5,
        ]);

        $response->assertForbidden();
    }

    public function test_staff_can_edit_own_monitoring(): void
    {
        ['staff' => $staff, 'tank' => $tank] = $this->setUpStaff();
        $monitoring = DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'staff_id' => $staff->id,
            'user_id' => null,
            'log_date' => '2026-08-03',
        ]);

        $response = $this->actingAs($staff, 'staff')->put(route('staff.monitoring.update', $monitoring), [
            'tank_id' => $tank->id,
            'log_date' => '2026-08-03',
            'ppm' => 800,
            'ph' => 6.6,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('daily_monitorings', ['id' => $monitoring->id, 'ppm' => 800]);
    }

    public function test_staff_cannot_edit_others_monitoring(): void
    {
        ['farm' => $farm, 'tank' => $tank] = $this->setUpStaff();
        $otherStaff = Staff::factory()->create(['farm_id' => $farm->id]);
        $monitoring = DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'staff_id' => $otherStaff->id,
            'user_id' => null,
        ]);

        $response = $this->actingAs($otherStaff, 'staff')->put(route('staff.monitoring.update', $monitoring), [
            'tank_id' => $tank->id,
            'log_date' => '2026-08-03',
            'ppm' => 800,
            'ph' => 6.6,
        ]);

        $response->assertForbidden();
    }

    public function test_staff_can_delete_own_monitoring(): void
    {
        ['staff' => $staff, 'tank' => $tank] = $this->setUpStaff();
        $monitoring = DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'staff_id' => $staff->id,
            'user_id' => null,
        ]);

        $response = $this->actingAs($staff, 'staff')->delete(route('staff.monitoring.destroy', $monitoring));

        $response->assertRedirect();
        $this->assertSoftDeleted('daily_monitorings', ['id' => $monitoring->id]);
    }
}
```

Catatan: pastikan factory `DailyMonitoringFactory` dan `TankFactory` ada. Jika `DailyMonitoringFactory` mengisi `user_id => User::factory()` secara default, test di atas meng-override `staff_id`/`user_id` secara eksplisit.

- [ ] **Step 5: Jalankan test, pastikan gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Staff/StaffMonitoringTest.php`
Expected: FAIL (route belum ada).

- [ ] **Step 6: Implementasi + test pass**

Buat file langkah 1–3. Run: `vendor/bin/sail artisan test --compact tests/Feature/Staff/StaffMonitoringTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Staff/StaffMonitoringController.php resources/views/staff/monitoring/create.blade.php resources/views/staff/monitoring/index.blade.php resources/views/staff/monitoring/edit.blade.php routes/staff.php tests/Feature/Staff/StaffMonitoringTest.php
git commit -m "feat: transaksi monitoring petugas lapangan"
```

---

### Task 10: Transaksi Staff — Nutrient Addition (AB Mix)

**Files:**
- Create: `app/Http/Controllers/Staff/StaffNutrientAdditionController.php`
- Create: `resources/views/staff/nutrient/create.blade.php`
- Create: `resources/views/staff/nutrient/index.blade.php`
- Create: `resources/views/staff/nutrient/edit.blade.php`
- Modify: `routes/staff.php`
- Test: `tests/Feature/Staff/StaffNutrientTest.php`

**Interfaces:**
- Consumes: `NutrientAddition` (kolom `staff_id`), guard `staff`.
- Produces: routes `staff.nutrient.index/create/store/edit/update/destroy`; controller dengan cek kepemilikan & scope farm (pola identik Task 9).

- [ ] **Step 1: Tulis controller**

`app/Http/Controllers/Staff/StaffNutrientAdditionController.php` — pola identik `StaffMonitoringController`, model `NutrientAddition`, validasi field `ppm_before`, `ppm_after`, `nutrient_a_ml`, `nutrient_b_ml`, `log_date`, `notes` (lihat validasi `NutrientAdditionController`), create dengan `staff_id` + `user_id => null`:

```php
<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\Tank;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StaffNutrientAdditionController extends Controller
{
    private function staff(): \App\Models\Farm\Staff
    {
        return auth('staff')->user();
    }

    private function farmTanks()
    {
        return Tank::where('farm_id', $this->staff()->farm_id)->orderBy('name')->get();
    }

    public function index(): View
    {
        $additions = NutrientAddition::where('staff_id', $this->staff()->id)
            ->with('tank')
            ->latest('log_date')
            ->paginate(20);

        return view('staff.nutrient.index', compact('additions'));
    }

    public function create(): View
    {
        return view('staff.nutrient.create', ['tanks' => $this->farmTanks()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ppm_before' => 'required|numeric|min:0|max:3000',
            'ppm_after' => 'required|numeric|min:0|max:3000|gt:ppm_before',
            'nutrient_a_ml' => 'required|numeric|min:0|max:10000',
            'nutrient_b_ml' => 'required|numeric|min:0|max:10000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $tank = Tank::where('id', $validated['tank_id'])
            ->where('farm_id', $this->staff()->farm_id)
            ->first();

        if (! $tank) {
            abort(403);
        }

        NutrientAddition::create($validated + [
            'staff_id' => $this->staff()->id,
            'user_id' => null,
        ]);

        return redirect()->route('staff.nutrient.index')
            ->with('success', 'Data AB Mix berhasil disimpan.');
    }

    public function edit(NutrientAddition $nutrientAddition): View
    {
        abort_unless($nutrientAddition->staff_id === $this->staff()->id, 403);

        return view('staff.nutrient.edit', [
            'nutrientAddition' => $nutrientAddition,
            'tanks' => $this->farmTanks(),
        ]);
    }

    public function update(Request $request, NutrientAddition $nutrientAddition): RedirectResponse
    {
        abort_unless($nutrientAddition->staff_id === $this->staff()->id, 403);

        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ppm_before' => 'required|numeric|min:0|max:3000',
            'ppm_after' => 'required|numeric|min:0|max:3000|gt:ppm_before',
            'nutrient_a_ml' => 'required|numeric|min:0|max:10000',
            'nutrient_b_ml' => 'required|numeric|min:0|max:10000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $tank = Tank::where('id', $validated['tank_id'])
            ->where('farm_id', $this->staff()->farm_id)
            ->first();

        if (! $tank) {
            abort(403);
        }

        $nutrientAddition->update($validated);

        return redirect()->route('staff.nutrient.index')
            ->with('success', 'Data AB Mix berhasil diperbarui.');
    }

    public function destroy(NutrientAddition $nutrientAddition): RedirectResponse
    {
        abort_unless($nutrientAddition->staff_id === $this->staff()->id, 403);

        $nutrientAddition->delete();

        return redirect()->route('staff.nutrient.index')
            ->with('success', 'Data AB Mix berhasil dihapus.');
    }
}
```

- [ ] **Step 2: Tulis views**

Salin pola `staff/monitoring/*` dari Task 9, ubah:
- `create.blade.php` → field: `ppm_before`, `ppm_after`, `nutrient_a_ml`, `nutrient_b_ml`, `notes`, `log_date`, `tank_id`; action `staff.nutrient.store`.
- `index.blade.php` → kolom: Tanggal, Tank, PPM Sebelum, PPM Sesudah, A; kolom aksi; `$additions`.
- `edit.blade.php` → isi nilai dari `$nutrientAddition`, action `staff.nutrient.update`.

- [ ] **Step 3: Tambah routes** (grup `auth:staff`)

```php
Route::get('/staff/nutrient', [StaffNutrientAdditionController::class, 'index'])->name('staff.nutrient.index');
Route::get('/staff/nutrient/create', [StaffNutrientAdditionController::class, 'create'])->name('staff.nutrient.create');
Route::post('/staff/nutrient/store', [StaffNutrientAdditionController::class, 'store'])->name('staff.nutrient.store');
Route::get('/staff/nutrient/{nutrientAddition}/edit', [StaffNutrientAdditionController::class, 'edit'])->name('staff.nutrient.edit');
Route::put('/staff/nutrient/{nutrientAddition}', [StaffNutrientAdditionController::class, 'update'])->name('staff.nutrient.update');
Route::delete('/staff/nutrient/{nutrientAddition}', [StaffNutrientAdditionController::class, 'destroy'])->name('staff.nutrient.destroy');
```

- [ ] **Step 4: Tulis test** `tests/Feature/Staff/StaffNutrientTest.php` — pola identik `StaffMonitoringTest`, model `NutrientAddition`, route `staff.nutrient.*`, 5 test: create, tank kebun lain ditolak, edit sendiri, tidak bisa edit punya orang lain, delete sendiri.

- [ ] **Step 5: Jalankan test, pastikan gagal → implement → pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Staff/StaffNutrientTest.php`
Expected: FAIL lalu PASS setelah implementasi.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Staff/StaffNutrientAdditionController.php resources/views/staff/nutrient/create.blade.php resources/views/staff/nutrient/index.blade.php resources/views/staff/nutrient/edit.blade.php routes/staff.php tests/Feature/Staff/StaffNutrientTest.php
git commit -m "feat: transaksi AB Mix petugas lapangan"
```

---

### Task 11: Transaksi Staff — pH Down

**Files:**
- Create: `app/Http/Controllers/Staff/StaffPhDownController.php`
- Create: `resources/views/staff/ph-down/create.blade.php`
- Create: `resources/views/staff/ph-down/index.blade.php`
- Create: `resources/views/staff/ph-down/edit.blade.php`
- Modify: `routes/staff.php`
- Test: `tests/Feature/Staff/StaffPhDownTest.php`

**Interfaces:**
- Consumes: `PhDownLog` (kolom `staff_id`), guard `staff`.
- Produces: routes `staff.ph-down.index/create/store/edit/update/destroy`; controller pola identik Task 10.

- [ ] **Step 1: Tulis controller** `StaffPhDownController` — model `PhDownLog`, validasi field `ph_before`, `ph_after` (`lt:ph_before`), `ph_down_ml`, `log_date`, `notes` (lihat `PhDownLogController`), create dengan `staff_id` + `user_id => null`.

- [ ] **Step 2: Tulis views** — salin pola `staff/monitoring/*`, field pH Down, route `staff.ph-down.*`.

- [ ] **Step 3: Tambah routes** (grup `auth:staff`)

```php
Route::get('/staff/ph-down', [StaffPhDownController::class, 'index'])->name('staff.ph-down.index');
Route::get('/staff/ph-down/create', [StaffPhDownController::class, 'create'])->name('staff.ph-down.create');
Route::post('/staff/ph-down/store', [StaffPhDownController::class, 'store'])->name('staff.ph-down.store');
Route::get('/staff/ph-down/{phDownLog}/edit', [StaffPhDownController::class, 'edit'])->name('staff.ph-down.edit');
Route::put('/staff/ph-down/{phDownLog}', [StaffPhDownController::class, 'update'])->name('staff.ph-down.update');
Route::delete('/staff/ph-down/{phDownLog}', [StaffPhDownController::class, 'destroy'])->name('staff.ph-down.destroy');
```

- [ ] **Step 4: Tulis test** `tests/Feature/Staff/StaffPhDownTest.php` — pola identik `StaffMonitoringTest`, model `PhDownLog`, route `staff.ph-down.*`.

- [ ] **Step 5: Jalankan test, pastikan gagal → implement → pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Staff/StaffPhDownTest.php`
Expected: FAIL lalu PASS setelah implementasi.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Staff/StaffPhDownController.php resources/views/staff/ph-down/create.blade.php resources/views/staff/ph-down/index.blade.php resources/views/staff/ph-down/edit.blade.php routes/staff.php tests/Feature/Staff/StaffPhDownTest.php
git commit -m "feat: transaksi pH Down petugas lapangan"
```

---

### Task 12: Laporan Staff

**Files:**
- Create: `app/Http/Controllers/Staff/StaffReportController.php`
- Create: `resources/views/staff/reports/monitoring.blade.php`
- Create: `resources/views/staff/reports/nutrient.blade.php`
- Create: `resources/views/staff/reports/ph-down.blade.php`
- Modify: `routes/staff.php`
- Test: `tests/Feature/Staff/StaffReportTest.php`

**Interfaces:**
- Consumes: `Tank`, `DailyMonitoring`, `NutrientAddition`, `PhDownLog`, guard `staff`.
- Produces: routes `staff.reports.monitoring/nutrient/ph-down`; controller dengan scope farm staff.

- [ ] **Step 1: Tulis controller**

`app/Http/Controllers/Staff/StaffReportController.php`:

```php
<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Tank;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StaffReportController extends Controller
{
    public function monitoring(Request $request): View
    {
        $farm = auth('staff')->user()->farm;
        $tanks = Tank::where('farm_id', $farm->id)->orderBy('name')->get();

        $aggregates = null;
        if ($request->filled(['tank_id', 'start_date', 'end_date'])) {
            $query = DailyMonitoring::where('tank_id', $request->input('tank_id'))
                ->whereBetween('log_date', [$request->input('start_date'), $request->input('end_date')]);

            $aggregates = (object) [
                'count' => $query->count(),
                'avg_ppm' => $query->avg('ppm'),
                'highest_ppm' => $query->max('ppm'),
                'lowest_ppm' => $query->min('ppm'),
                'avg_ph' => $query->avg('ph'),
                'highest_ph' => $query->max('ph'),
                'lowest_ph' => $query->min('ph'),
            ];
        }

        return view('staff.reports.monitoring', compact('tanks', 'aggregates'));
    }

    public function nutrient(Request $request): View
    {
        $farm = auth('staff')->user()->farm;
        $tanks = Tank::where('farm_id', $farm->id)->orderBy('name')->get();

        $aggregates = null;
        if ($request->filled(['tank_id', 'start_date', 'end_date'])) {
            $query = NutrientAddition::where('tank_id', $request->input('tank_id'))
                ->whereBetween('log_date', [$request->input('start_date'), $request->input('end_date')]);

            $aggregates = (object) [
                'count' => $query->count(),
                'total_nutrient_a_ml' => $query->sum('nutrient_a_ml'),
                'total_nutrient_b_ml' => $query->sum('nutrient_b_ml'),
            ];
        }

        return view('staff.reports.nutrient', compact('tanks', 'aggregates'));
    }

    public function phDown(Request $request): View
    {
        $farm = auth('staff')->user()->farm;
        $tanks = Tank::where('farm_id', $farm->id)->orderBy('name')->get();

        $aggregates = null;
        if ($request->filled(['tank_id', 'start_date', 'end_date'])) {
            $query = PhDownLog::where('tank_id', $request->input('tank_id'))
                ->whereBetween('log_date', [$request->input('start_date'), $request->input('end_date')]);

            $aggregates = (object) [
                'count' => $query->count(),
                'total_ph_down_ml' => $query->sum('ph_down_ml'),
            ];
        }

        return view('staff.reports.ph-down', compact('tanks', 'aggregates'));
    }
}
```

- [ ] **Step 2: Tulis views** — salin isi `reports/monitoring.blade.php`, `reports/nutrient.blade.php`, `reports/ph-down.blade.php` dari user-side, tapi:
- Ganti `@extends('layouts.app')` → `@extends('layouts.staff')`.
- Hapus blok `@include('partials.sidebar')`, `@include('partials.topbar')`, dan bungkus `<main>` (layout staff sudah menyediakan).
- Ganti `action="{{ route('reports.monitoring') }}"` → `action="{{ route('staff.reports.monitoring') }}"` (dan setara untuk nutrient/ph-down).
- Hapus variabel `$tankId`, `$startDate`, `$endDate` dari compact — view memakai `request('tank_id')` / `request('start_date')` / `request('end_date')` untuk menandai option terpilih (atau pass kembali dari controller: `'tankId' => $request->input('tank_id')` dst.). Gunakan `request()` di view untuk menjaga kesederhanaan.

- [ ] **Step 3: Tambah routes** (grup `auth:staff`)

```php
Route::get('/staff/reports/monitoring', [StaffReportController::class, 'monitoring'])->name('staff.reports.monitoring');
Route::get('/staff/reports/nutrient', [StaffReportController::class, 'nutrient'])->name('staff.reports.nutrient');
Route::get('/staff/reports/ph-down', [StaffReportController::class, 'phDown'])->name('staff.reports.ph-down');
```

- [ ] **Step 4: Tulis test** `tests/Feature/Staff/StaffReportTest.php`:

```php
<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_can_view_monitoring_report(): void
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);
        $tank = Tank::factory()->create(['farm_id' => $farm->id]);
        DailyMonitoring::factory()->create(['tank_id' => $tank->id, 'ppm' => 700, 'ph' => 6.5, 'log_date' => '2026-08-01']);

        $response = $this->actingAs($staff, 'staff')->get(route('staff.reports.monitoring', [
            'tank_id' => $tank->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]));

        $response->assertOk();
        $response->assertSee('700');
    }
}
```

Tambahkan test untuk `staff.reports.nutrient` dan `staff.reports.ph-down` dengan pola serupa.

- [ ] **Step 5: Jalankan test, pastikan gagal → implement → pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Staff/StaffReportTest.php`
Expected: FAIL lalu PASS setelah implementasi.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Staff/StaffReportController.php resources/views/staff/reports/monitoring.blade.php resources/views/staff/reports/nutrient.blade.php resources/views/staff/reports/ph-down.blade.php routes/staff.php tests/Feature/Staff/StaffReportTest.php
git commit -m "feat: laporan petugas lapangan"
```

---

### Task 13: Kelola Akun Staff (Oleh Owner/Manager)

**Files:**
- Create: `app/Http/Controllers/Farm/FarmStaffController.php`
- Create: `app/Http/Requests/Farm/StoreStaffRequest.php`
- Create: `app/Http/Requests/Farm/UpdateStaffPasswordRequest.php`
- Create: `resources/views/farm-members/staff-create.blade.php`
- Modify: `resources/views/farm-members/index.blade.php`
- Modify: `routes/farm.php`
- Test: `tests/Feature/FarmStaff/FarmStaffTest.php`

**Interfaces:**
- Consumes: `FarmPolicy::manageStaff` (owner|manager), model `Staff` dari Task 1.
- Produces: routes `farm.members.staff-create/store/staff-password/staff-toggle/staff-destroy`; `FarmStaffController::create/store/resetPassword/toggle/destroy`.

- [ ] **Step 1: Tulis request**

`app/Http/Requests/Farm/StoreStaffRequest.php`:

```php
<?php

namespace App\Http\Requests\Farm;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageStaff', $this->route('farm'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $farmId = $this->route('farm')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required', 'string', 'max:255',
                Rule::unique('staff', 'username')
                    ->where('farm_id', $farmId)
                    ->whereNull('deleted_at'),
            ],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama petugas wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah dipakai di kebun ini.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
        ];
    }
}
```

`app/Http/Requests/Farm/UpdateStaffPasswordRequest.php`:

```php
<?php

namespace App\Http\Requests\Farm;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageStaff', $this->route('farm'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
```

- [ ] **Step 2: Tulis controller**

`app/Http/Controllers/Farm/FarmStaffController.php`:

```php
<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreStaffRequest;
use App\Http\Requests\Farm\UpdateStaffPasswordRequest;
use App\Models\Farm;
use App\Models\Farm\Staff;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class FarmStaffController extends Controller
{
    public function create(Farm $farm): View
    {
        $this->authorize('manageStaff', $farm);

        return view('farm-members.staff-create', compact('farm'));
    }

    public function store(StoreStaffRequest $request, Farm $farm): RedirectResponse
    {
        $validated = $request->validated();

        $farm->staff()->create($validated);

        return redirect()->route('farm.members.index', $farm)
            ->with('success', 'Akun petugas berhasil dibuat.');
    }

    public function resetPassword(UpdateStaffPasswordRequest $request, Farm $farm, Staff $staff): RedirectResponse
    {
        abort_unless($staff->farm_id === $farm->id, 404);

        $staff->update(['password' => $request->validated('password')]);

        return redirect()->route('farm.members.index', $farm)
            ->with('success', 'Password petugas berhasil direset.');
    }

    public function toggle(Farm $farm, Staff $staff): RedirectResponse
    {
        $this->authorize('manageStaff', $farm);
        abort_unless($staff->farm_id === $farm->id, 404);

        $staff->update(['is_active' => ! $staff->is_active]);

        return redirect()->route('farm.members.index', $farm)
            ->with('success', $staff->is_active ? 'Akun petugas diaktifkan.' : 'Akun petugas dinonaktifkan.');
    }

    public function destroy(Farm $farm, Staff $staff): RedirectResponse
    {
        $this->authorize('manageStaff', $farm);
        abort_unless($staff->farm_id === $farm->id, 404);

        $staff->delete();

        return redirect()->route('farm.members.index', $farm)
            ->with('success', 'Akun petugas dihapus.');
    }
}
```

Catatan: `password` di-cast `hashed` oleh model Staff (Task 1), jadi `update(['password' => ...])` otomatis di-hash.

- [ ] **Step 3: Tulis view**

`resources/views/farm-members/staff-create.blade.php` — form (extends `layouts.app` + sidebar/topbar, pola `farm-members/create.blade.php`) dengan field `name`, `username`, `password`, action `route('farm.members.staff-store', $farm)`:

```blade
@extends('layouts.app')

@section('title', 'Tambah Petugas Lapangan')

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-2xl">
                    <a href="{{ route('farm.members.index', $farm) }}" class="inline-flex items-center gap-2 text-sm text-slate-500 transition hover:text-slate-700">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <div class="mt-4 rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5 sm:p-8">
                        <h2 class="text-lg font-semibold text-slate-900">Tambah Petugas Lapangan</h2>
                        <p class="mt-1 text-sm text-slate-500">Buat akun petugas untuk kebun <strong>{{ $farm->name }}</strong>.</p>

                        <form action="{{ route('farm.members.staff-store', $farm) }}" method="POST" class="mt-6 space-y-5">
                            @csrf

                            <div>
                                <label for="name" class="block text-sm font-semibold text-slate-700">Nama Petugas</label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20 @error('name') border-red-300 @enderror">
                                @error('name')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="username" class="block text-sm font-semibold text-slate-700">Username</label>
                                <input type="text" name="username" id="username" value="{{ old('username') }}" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20 @error('username') border-red-300 @enderror">
                                @error('username')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>
                                <input type="password" name="password" id="password" required minlength="8"
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20 @error('password') border-red-300 @enderror">
                                @error('password')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex items-center gap-3">
                                <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-6 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                                    <i class="bi bi-person-plus"></i>
                                    Buat Akun
                                </button>
                                <a href="{{ route('farm.members.index', $farm) }}"
                                    class="rounded-2xl px-6 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">
                                    Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    </div>
@endsection
```

- [ ] **Step 4: Update view `farm-members/index.blade.php`**

1. `FarmUserController::index` load staff: ubah menjadi `$farm->load(['users' => ..., 'staff'])` (di Task 13 langkah controller; tambahkan di `FarmUserController::index`).
2. Tambahkan tombol "Tambah Petugas" di header (di samping "Undang Anggota") yang mengarah ke `route('farm.members.staff-create', $farm)`, hanya jika `@can('manageStaff', $farm)`.
3. Tambahkan section baru **"Petugas Lapangan"** di bawah tabel member, berisi tabel staff kebun: Nama, Username, Status (Aktif/Nonaktif), Aksi (Reset Password, Nonaktifkan/Aktifkan, Hapus). Form Reset Password pakai prompt JS sederhana atau halaman terpisah — untuk kesederhanaan, buat tombol reset yang membuka halaman `staff-password` atau pakai modal. **Pilih pendekatan paling sederhana:** tambahkan tombol "Reset Password" yang mengarah ke halaman `farm-members/staff-password.blade.php` (form password baru), route `farm.members.staff-password` GET. Karena plan ini sudah panjang, gunakan tombol toggle + hapus langsung, dan reset password via form inline pada halaman index menggunakan `<details>` element.

   Implementasi reset password sederhana pada halaman index (pakai `<details>`):

   ```blade
   <details class="relative">
       <summary class="cursor-pointer rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">Reset Password</summary>
       <form action="{{ route('farm.members.staff-password', [$farm, $staff]) }}" method="POST" class="mt-2 flex items-center gap-2">
           @csrf
           @method('PUT')
           <input type="password" name="password" required minlength="8" placeholder="Password baru"
               class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs">
           <button type="submit" class="rounded-xl bg-[#ffce54] px-3 py-1.5 text-xs font-bold text-[#1a1c1e]">Simpan</button>
       </form>
   </details>
   ```

- [ ] **Step 5: Tambah routes** di `routes/farm.php` (di dalam grup member):

```php
Route::get('/{farm}/members/staff/create', [FarmStaffController::class, 'create'])->name('members.staff-create');
Route::post('/{farm}/members/staff', [FarmStaffController::class, 'store'])->name('members.staff-store');
Route::put('/{farm}/members/staff/{staff}/password', [FarmStaffController::class, 'resetPassword'])->name('members.staff-password');
Route::put('/{farm}/members/staff/{staff}/toggle', [FarmStaffController::class, 'toggle'])->name('members.staff-toggle');
Route::delete('/{farm}/members/staff/{staff}', [FarmStaffController::class, 'destroy'])->name('members.staff-destroy');
```

Tambahkan `use App\Http\Controllers\Farm\FarmStaffController;` di `routes/farm.php`.

- [ ] **Step 6: Tulis test**

`tests/Feature/FarmStaff/FarmStaffTest.php`:

```php
<?php

namespace Tests\Feature\FarmStaff;

use App\Models\Farm;
use App\Models\Farm\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FarmStaffTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setUpFarm(): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        session()->put('selected_farm_id', $farm->id);

        return compact('owner', 'farm');
    }

    public function test_owner_can_create_staff(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();

        $response = $this->actingAs($owner)->post(route('farm.members.staff-store', $farm), [
            'name' => 'Anton',
            'username' => 'anton',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('farm.members.index', $farm));
        $this->assertDatabaseHas('staff', [
            'farm_id' => $farm->id,
            'username' => 'anton',
        ]);
    }

    public function test_manager_can_create_staff(): void
    {
        ['farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($manager)->post(route('farm.members.staff-store', $farm), [
            'name' => 'Anton',
            'username' => 'anton',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('staff', ['farm_id' => $farm->id, 'username' => 'anton']);
    }

    public function test_username_unique_per_farm_on_create(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        Staff::factory()->create(['farm_id' => $farm->id, 'username' => 'anton']);

        $response = $this->actingAs($owner)->post(route('farm.members.staff-store', $farm), [
            'name' => 'Anton Lain',
            'username' => 'anton',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_owner_can_deactivate_and_reactivate_staff(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $this->actingAs($owner)->put(route('farm.members.staff-toggle', [$farm, $staff]));
        $this->assertFalse($staff->fresh()->is_active);

        $this->actingAs($owner)->put(route('farm.members.staff-toggle', [$farm, $staff]));
        $this->assertTrue($staff->fresh()->is_active);
    }

    public function test_owner_can_reset_staff_password(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $response = $this->actingAs($owner)->put(route('farm.members.staff-password', [$farm, $staff]), [
            'password' => 'newsecret123',
        ]);

        $response->assertRedirect();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newsecret123', $staff->fresh()->password));
    }

    public function test_owner_can_delete_staff(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $response = $this->actingAs($owner)->delete(route('farm.members.staff-destroy', [$farm, $staff]));

        $response->assertRedirect();
        $this->assertSoftDeleted('staff', ['id' => $staff->id]);
    }

    public function test_plain_member_cannot_manage_staff(): void
    {
        ['farm' => $farm] = $this->setUpFarm();
        $member = User::factory()->create();
        $farm->users()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($member)->post(route('farm.members.staff-store', $farm), [
            'name' => 'X',
            'username' => 'x',
            'password' => 'secret123',
        ]);

        $response->assertForbidden();
    }
}
```

- [ ] **Step 7: Jalankan test, pastikan gagal → implement → pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/FarmStaff/FarmStaffTest.php`
Expected: FAIL lalu PASS setelah implementasi.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Farm/FarmStaffController.php app/Http/Requests/Farm/StoreStaffRequest.php app/Http/Requests/Farm/UpdateStaffPasswordRequest.php resources/views/farm-members/staff-create.blade.php resources/views/farm-members/index.blade.php routes/farm.php tests/Feature/FarmStaff/FarmStaffTest.php
git commit -m "feat: kelola akun petugas lapangan (owner/manager)"
```

---

### Task 14: Atribusi Staff pada Activity Log + Otorisasi Akhir

**Files:**
- Modify: `app/Observers/ActivityLogObserver.php`
- Modify: `resources/views/activity-logs/index.blade.php`
- Modify: `tests/Feature/Farm/FarmTest.php` (jika perlu)
- Test: `tests/Feature/Staff/StaffActivityLogTest.php`

**Interfaces:**
- Consumes: kolom `activity_logs.staff_id` (Task 2).
- Produces: observer mendeteksi guard `staff` dan mengisi `staff_id`.

- [ ] **Step 1: Update observer**

`app/Observers/ActivityLogObserver.php` — ubah method `record`:

```php
private function record(string $action, Farm|Tank|DailyMonitoring|NutrientAddition|PhDownLog $entity): void
{
    if ($entity instanceof Farm) {
        $farmId = $entity->id;
    } elseif ($entity instanceof Tank) {
        $farmId = $entity->farm_id;
    } else {
        $farmId = $entity->tank?->farm_id;
    }

    $userId = auth()->id();
    $staffId = auth('staff')->id();

    if (! $farmId || (! $userId && ! $staffId)) {
        return;
    }

    $entityType = class_basename($entity);
    $entityType = strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($entityType)));

    $name = match (true) {
        $entity instanceof Farm, $entity instanceof Tank => $entity->name,
        default => "#{$entity->id}",
    };

    ActivityLog::create([
        'farm_id' => $farmId,
        'user_id' => $userId,
        'staff_id' => $staffId,
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entity->id,
        'description' => ucfirst("{$action} {$entityType} {$name}"),
        'created_at' => now(),
    ]);
}
```

- [ ] **Step 2: Update view activity log**

`resources/views/activity-logs/index.blade.php` — pada kolom pelaku, tampilkan:

```blade
{{ $log->user?->name ?? $log->staff?->name ?? '—' }}
```

- [ ] **Step 3: Tulis test**

`tests/Feature/Staff/StaffActivityLogTest.php`:

```php
<?php

namespace Tests\Feature\Staff;

use App\Models\Farm\ActivityLog;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffActivityLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_creation_logs_staff_id(): void
    {
        $farm = \App\Models\Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);
        $tank = Tank::factory()->create(['farm_id' => $farm->id]);

        $this->actingAs($staff, 'staff')->post(route('staff.monitoring.store'), [
            'tank_id' => $tank->id,
            'log_date' => '2026-08-03',
            'ppm' => 700,
            'ph' => 6.5,
        ]);

        $log = ActivityLog::where('entity_type', 'daily_monitoring')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($staff->id, $log->staff_id);
        $this->assertNull($log->user_id);
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan gagal → implement → pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Staff/StaffActivityLogTest.php`
Expected: FAIL (observer belum set staff_id — `user_id` null & `staff_id` null → record di-skip) lalu PASS setelah implementasi.

- [ ] **Step 5: Jalankan seluruh suite + pint**

Run: `vendor/bin/sail artisan test --compact`
Expected: seluruh test pass.
Run: `vendor/bin/sail bin pint --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Observers/ActivityLogObserver.php resources/views/activity-logs/index.blade.php tests/Feature/Staff/StaffActivityLogTest.php
git commit -m "feat: atribusi staff pada activity log"
```

---

## Self-Review (diisi oleh penulis plan)

- **Cakupan spec:** Semua bagian spec (role model, tabel staff, staff_id, login staff, layout staff, transaksi, laporan, kelola staff, transfer owner, activity log) dipetakan ke Task 1–14. ✓
- **Placeholder scan:** Semua langkah berisi kode nyata; tidak ada "TBD"/"TODO". Test pada Task 10/11 dirujuk sebagai pola identik Task 9 dengan nama file & model ditentukan — jika ragu, eksekutor membaca Task 9 secara penuh. ✓
- **Type consistency:** Model `App\Models\Farm\Staff`, guard `staff`, routes `staff.*`, `farm.members.staff-*` konsisten antar task. ✓
