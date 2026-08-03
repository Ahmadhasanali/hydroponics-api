# Hapus Unique Index `users.name` — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menghapus constraint unique pada kolom `users.name` agar dua user boleh berbagi nama tampilan yang sama.

**Architecture:** Satu migrasi baru yang drop index `users_name_unique` (pgsql). Tidak ada perubahan di layer aplikasi karena `name` tidak pernah divalidasi unik (hanya `email` yang unik di `RegisterRequest` dan `StoreUserRequest`). Diperbarui schema dump agar database test tidak memuat constraint, plus 2 test regresi.

**Tech Stack:** Laravel 13 (PHP 8.5), Laravel Sail, PostgreSQL (pgsql), PHPUnit 12, Pint.

**Spec:** `docs/superpowers/specs/2026-08-03-drop-unique-name-design.md`

## Global Constraints

- Semua perintah artisan/phpunit/pint lewat Sail: `vendor/bin/sail artisan ...`, `vendor/bin/sail bin pint --dirty --format agent`.
- TDD: tulis test gagal → jalankan → implementasi minimal → test hijau → commit.
- Test wajib: `vendor/bin/sail artisan test --compact --filter=<NamaTest>`; seluruh suite: `vendor/bin/sail artisan test --compact`.
- Jangan hapus test yang ada. Semua test PHPUnit (bukan Pest). Gunakan `LazilyRefreshDatabase`.
- Tanpa dependency composer baru; tanpa variabel ENV baru.
- Jangan mengedit migrasi lama `2026_06_27_000000_add_unique_index_to_users_name_column.php` yang sudah dijalankan di produksi.
- Commit dengan pesan `feat:` / `test:` deskriptif.

## File Structure

**Dibuat:**
- `database/migrations/2026_08_03_000000_drop_unique_index_on_users_name_column.php` — drop index `users_name_unique`, down() mengembalikannya.

**Dimodifikasi:**
- `tests/Feature/User/UserTest.php` — tambah test admin boleh membuat 2 user dengan `name` sama.
- `tests/Feature/Auth/RegistrationTest.php` — tambah test registrasi dengan `name` duplikat tetap sukses.
- `database/schema/pgsql-schema.sql` — diperbarui otomatis via `php artisan schema:dump --prune` (constraint `users_name_unique` hilang).

---

### Task 1: Drop unique index pada `users.name` + test regresi

**Files:**
- Create: `database/migrations/2026_08_03_000000_drop_unique_index_on_users_name_column.php`
- Modify: `tests/Feature/User/UserTest.php` (tambah 1 test)
- Modify: `tests/Feature/Auth/RegistrationTest.php` (tambah 1 test)
- Modify: `database/schema/pgsql-schema.sql` (via schema:dump)

**Interfaces:**
- Consumes: kolom `users.name` (string, existing), constraint `users_name_unique` (dibuat oleh migrasi `2026_06_27_000000_add_unique_index_to_users_name_column`).
- Produces: tidak ada signature baru untuk task lain; hanya menghapus constraint DB.

- [ ] **Step 1: Tulis test gagal — admin boleh buat 2 user dengan `name` sama**

Tambahkan test berikut di `tests/Feature/User/UserTest.php` (setelah `test_admin_store_creates_verified_user`):

```php
public function test_admin_can_store_two_users_with_same_name(): void
{
    User::factory()->create(['name' => 'Petani Sama']);
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('user.store'), [
        'name' => 'Petani Sama',
        'email' => 'beda@example.com',
    ]);

    $response->assertRedirect(route('user.index'));
    $this->assertDatabaseHas('users', [
        'name' => 'Petani Sama',
        'email' => 'beda@example.com',
    ]);
}
```

Tambahkan test berikut di `tests/Feature/Auth/RegistrationTest.php` (setelah `test_registration_requires_confirmed_password`):

```php
public function test_user_can_register_with_duplicate_name(): void
{
    Notification::fake();

    User::factory()->create(['name' => 'Petani Baru']);

    $response = $this->post(route('register'), [
        'name' => 'Petani Baru',
        'email' => 'lain@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect('/email/verify');
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'name' => 'Petani Baru',
        'email' => 'lain@example.com',
    ]);
}
```

- [ ] **Step 2: Jalankan kedua test, pastikan GAGAL karena constraint unique**

Run: `vendor/bin/sail artisan test --compact --filter=test_admin_can_store_two_users_with_same_name`
Expected: FAIL — query exception "duplicate key value violates unique constraint users_name_unique".

Run: `vendor/bin/sail artisan test --compact --filter=test_user_can_register_with_duplicate_name`
Expected: FAIL — query exception serupa.

- [ ] **Step 3: Tulis migrasi drop index**

Buat `database/migrations/2026_08_03_000000_drop_unique_index_on_users_name_column.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique('name');
        });
    }
};
```

- [ ] **Step 4: Jalankan migrasi**

Run: `vendor/bin/sail artisan migrate`
Expected: `2026_08_03_000000_drop_unique_index_on_users_name_column ................................ RUNNING` → `DONE`.

- [ ] **Step 5: Jalankan kedua test, pastikan HIJAU**

Run: `vendor/bin/sail artisan test --compact --filter=test_admin_can_store_two_users_with_same_name`
Expected: PASS.

Run: `vendor/bin/sail artisan test --compact --filter=test_user_can_register_with_duplicate_name`
Expected: PASS.

- [ ] **Step 6: Jalankan seluruh suite auth/user, pastikan tidak ada regresi**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Auth/RegistrationTest.php tests/Feature/User/UserTest.php`
Expected: semua PASS.

- [ ] **Step 7: Perbarui schema dump**

Run: `vendor/bin/sail artisan schema:dump --prune`
Expected: `database/schema/pgsql-schema.sql` terbarui; pastikan tidak ada lagi baris `ADD CONSTRAINT users_name_unique UNIQUE (name);`:

```bash
rg -n "users_name_unique" database/schema/pgsql-schema.sql
```

Expected: no output (constraint sudah hilang).

- [ ] **Step 8: Format dengan Pint**

Run: `vendor/bin/sail bin pint --dirty --format agent`
Expected: file diformat (apabila perlu).

- [ ] **Step 9: Jalankan seluruh suite**

Run: `vendor/bin/sail artisan test --compact`
Expected: 188/188 PASS (186 sebelumnya + 2 test baru).

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_08_03_000000_drop_unique_index_on_users_name_column.php database/schema/pgsql-schema.sql tests/Feature/User/UserTest.php tests/Feature/Auth/RegistrationTest.php
git commit -m "feat: hapus unique index kolom name pada users"
```

---

## Verification

- `git log --oneline -1` menampilkan commit baru.
- `rg -n "users_name_unique" database/schema/pgsql-schema.sql` tidak menghasilkan output.
- Dua user dengan `name` identik bisa dibuat (dibuktikan oleh 2 test baru).
