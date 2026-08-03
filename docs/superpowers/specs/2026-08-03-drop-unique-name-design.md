# Design: Hapus Unikname Kolom `name` pada Tabel `users`

Tanggal: 2026-08-03
Status: DRAFT (menunggu review)

## Ringkasan

Kolom `name` pada tabel `users` saat ini memiliki constraint unik `users_name_unique`
di database, ditambahkan lewat migrasi `2026_06_27_000000_add_unique_index_to_users_name_column.php`.
Constraint ini tidak pernah di-enforce di layer aplikasi (tidak ada rule `unique:users,name`
di `RegisterRequest` maupun `StoreUserRequest`). Constraint murni DB ini memblokir dua user
dengan nama tampilan (display name) yang sama.

## Perubahan

Hanya 1 file migrasi baru:

- `database/migrations/2026_08_03_000000_drop_unique_index_on_users_name_column.php`
  - `up()`: `Schema::table('users', fn (Blueprint $table) => $table->dropUnique('users_name_unique'));`
  - `down()`: `Schema::table('users', fn (Blueprint $table) => $table->unique('name'));`
    (mengembalikan `users_name_unique`)

Migrasi baru (bukan mengedit migrasi lama yang sudah dijalankan) agar konsisten dengan
riwayat migrasi produksi.

## Non-perubahan

- Tidak ada perubahan controller/request/validation — `name` sudah tidak dibatasi unik
  di layer aplikasi.
- Tidak mengubah `RegisterRequest` / `StoreUserRequest` / `UserController`.
- Kolom `email` tetap unik (`unique:users,email`) — tidak disentuh.

## Schema Dump

Jalankan `php artisan schema:dump --prune` agar `database/schema/pgsql-schema.sql`
diperbarui menghapus constraint `users_name_unique`. Ini memastikan database test
(built via `RefreshDatabase`/`LazilyRefreshDatabase`) juga tidak memuat constraint.

## Pengujian

Tambah test yang membuktikan dua user boleh berbagi `name` yang sama:

- `tests/Feature/User/UserTest.php`:
  `test_admin_store_user_allows_duplicate_name` — admin membuat user, lalu membuat user
  kedua dengan `name` yang sama persis; assert sukses (redirect ke `user.index`, DB has 2 rows).
- `tests/Feature/Auth/RegistrationTest.php`:
  `test_user_can_register_with_duplicate_name` — user dengan `name` sama dengan user yang
  sudah ada tetap berhasil register (email berbeda); assert redirect `verification.notice`.

## Sukses Criteria

1. Migrasi `drop unique` berjalan (`php artisan migrate`).
2. `schema:dump` tidak lagi memuat `users_name_unique`.
3. Test baru hijau; seluruh suite (186 test) tetap hijau.
4. Dua user dengan `name` identik bisa dibuat (via admin store dan via registrasi publik).
