# Login Email + Forgot Password — Design

> **Status:** Approved
> **Date:** 2026-08-02
> **Project:** Hydroponic Farm Management System (Laravel 13)

## Latar Belakang & Tujuan

Saat ini login memakai field `username` yang dipetakan ke kolom `name`
(`AuthController::login`, `LoginRequest`). Tabel `users` **tidak memiliki kolom
`email`**, sehingga pengguna tidak punya alamat email dan tidak ada jalur reset
password.

**Tujuan:**
1. Ganti login menjadi **berbasis email + password** (hanya email).
2. Tambah fitur **forgot password**: sistem mengirim email (via Resend) berisi
   link untuk mereset/update password.
3. (Ditunda) Google OAuth — tidak termasuk dalam spec ini.

## Keputusan Kunci

- **Perluas auth custom yang ada** (`AuthController` + `Password::broker()`
  bawaan Laravel). Tidak menggunakan Fortify/Breeze agar arsitektur & UI custom
  yang ada tetap utuh.
- **Login hanya via email** (keputusan user). Username/nama tidak lagi dipakai
  untuk login.
- **User lama tanpa email** di-backfill dengan email sementara `<name>@mail.local`
  (mis. `superadmin@mail.local`, `hasan@mail.local`) sehingga tetap bisa login
  dan dapat mereset password (lalu mengganti email bila perlu).
- **Tabel `password_reset_tokens` direbuild** ke schema standar Laravel (`email`
  sebagai PK) agar `Password::broker()` bekerja tanpa kustomisasi.
- **Notifikasi reset kustom** (Mail) dengan template blade ber-brand, dikirim
  via Resend (sudah terkonfigurasi).
- **Anti-enumeration**: POST `/forgot-password` selalu menampilkan pesan sukses
  yang sama baik email terdaftar maupun tidak.

## Lingkup

### In Scope
- Migrasi kolom `email` di `users` (unique, not null, backfill).
- Rebuild migrasi `password_reset_tokens`.
- Login email-only: `LoginRequest`, `AuthController`, `login.blade.php`, `LoginTest`.
- Forgot password: route, `PasswordResetController`, notifikasi mail kustom,
  view `forgot-password` & `reset-password`.
- Pembuatan user admin: `StoreUserRequest` + `UserController::store` wajib email.
- Update `UserFactory`, seeder, dan test terkait.

### Out of Scope / Deferred
- Google OAuth (ditunda atas permintaan user).
- Verifikasi email (`email_verified_at`) — tidak diminta.
- Edit email di profil — hanya reset password; email diubah via admin/DB.
- UI halaman admin "kelola user" (view `user/index.blade.php` masih stub; hanya
  backend request/controller yang disesuaikan).

## Arsitektur

```
┌──────────────────────────────────────────────────────────────┐
│  WEB (guest)                                                 │
│  • GET  /login                  AuthController::showLoginForm │
│  • POST /login  (throttle:login) AuthController::login        │
│  • GET  /forgot-password        PasswordResetController::      │
│  • POST /forgot-password        showLinkRequestForm /          │
│  • GET  /reset-password/{token} sendResetLinkEmail /           │
│  • POST /reset-password         showResetForm / reset          │
├──────────────────────────────────────────────────────────────┤
│  LARAVEL AUTH                                                 │
│  • Auth::attempt(['email', 'password'])                        │
│  • Password::broker()  → DatabaseTokenRepository               │
│    (tabel password_reset_tokens, schema standar)               │
│  • Password::reset()   → reset + hapus token                   │
│  • User::sendPasswordResetNotification($token)                 │
├──────────────────────────────────────────────────────────────┤
│  MAIL (Resend)                                                │
│  • ResetPasswordNotification → ResetPasswordMail blade         │
└──────────────────────────────────────────────────────────────┘
```

## Detail Komponen

### 1. Migrasi `email` di `users`
1. `$table->string('email')->nullable()->after('name')`.
2. Backfill semua user yang `email IS NULL` dengan `Str::lower(name).'@mail.local'`.
3. Ubah `email` menjadi `unique` + `not null`.

### 2. Rebuild `password_reset_tokens`
- Drop tabel lama (`id`, `user_id`, `token`, `created_at`) → recreate schema
  standar Laravel:
  ```
  $table->string('email')->primary();
  $table->string('token');
  $table->timestamp('created_at')->nullable();
  ```

### 3. `User` model
- Tambah `email` ke `#[Fillable]`.
- Tambah override `sendPasswordResetNotification(string $token)` yang mengirim
  `ResetPasswordNotification($token)`.

### 4. Login email-only
- `LoginRequest`: `email` => `required|string|email`, `password` => `required|string`.
  Pesan: "Email wajib diisi.", "Password wajib diisi."
- `AuthController::login`: `Auth::attempt(['email' => ..., 'password' => ...])`;
  error pada key `email` ("Email atau password salah.").
- `login.blade.php`: input `email` (ikon envelope, `type="email"`,
  `autocomplete="email"`), hapus referensi `username`; tambah link
  "Lupa kata sandi?" → `route('password.request')` di baris "Ingat saya".

### 5. Route forgot password (guest)
```php
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])
        ->name('password.email')->middleware('throttle:6,1');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])
        ->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->name('password.store')->middleware('throttle:6,1');
});
```
- `sendResetLinkEmail`: `Password::broker()->sendResetLink(['email' => ...])`;
  redirect back + `status` flash sukses generik ("Jika email terdaftar, link
  reset telah dikirim ke email Anda.") apa pun hasilnya.
- `showResetForm`: render form reset dengan `token` + `email` (dari request).
- `reset`: `Password::broker()->reset($credentials, callback update password)`;
  sukses → delete token, `Auth::login($user)`, redirect `dashboard` + flash
  sukses; gagal (invalid/expired token) → `__('The reset link is invalid or
  expired.')` redirect back.

### 6. Notifikasi reset (Mail)
- `App\Notifications\ResetPasswordNotification` extends
  `Illuminate\Auth\Notifications\ResetPassword`, override `toMail` agar memakai
  view blade kustom `email.reset-password.blade.php` (brand "Kita Tumbuh",
  tombol `route('password.reset', $token)` + `$notifiable->getEmailForPasswordReset()`).

### 7. View baru
- `auth.forgot-password.blade.php`: form input email + tombol kirim link,
  tautan kembali ke login. Gaya glassmorphism sama dengan `auth/login.blade.php`
  (dark, accent `#ffce54`).
- `auth.reset-password.blade.php`: hidden email, password baru, konfirmasi,
  validasi client-side sederhana, tautan kembali ke login.

### 8. Pembuatan user (admin)
- `StoreUserRequest`: `name` => `required|string|max:255`,
  `email` => `required|string|lowercase|email|max:255|unique:users,email`.
- `UserController::store`: simpan `name` + `email` + password random.

## Alur Data

**Forgot password:**
1. User membuka `/forgot-password`, isi email, submit POST `/forgot-password`.
2. Broker membuat token, menyimpan di `password_reset_tokens` (berlaku 60 menit).
3. `User::sendPasswordResetNotification($token)` → notifikasi mail via Resend.
4. User klik link `GET /reset-password/{token}?email=...`.
5. User isi password baru, submit POST `/reset-password`.
6. Broker validasi token/email; sukses → password di-hash (`hashed` cast) &
   disimpan, token dihapus, user langsung di-login, redirect ke dashboard.

## Error Handling

- **Email tidak terdaftar**: tidak membocorkan keberadaan email (pesan sukses
  generik), sesuai pola Laravel.
- **Token invalid/expired**: pesan "Link reset tidak valid atau sudah kedaluwarsa.",
  redirect back; token expired dibersihkan otomatis oleh broker.
- **Rate limit**: POST forgot-password & reset dibatasi 6 permintaan/menit.
- **Gagal kirim email (Resend)**: `TransportException` dilempar; pada development
  bisa terlihat di log. Tidak mengubah alur utama aplikasi.

## Testing

- **`tests/Feature/Auth/LoginTest.php` (update)**: login dengan email; user tanpa
  email tidak bisa login; email & password wajib; error key `email`; logout.
- **`tests/Feature/Auth/PasswordResetTest.php` (baru)**:
  - Halaman forgot password & reset dapat dirender.
  - POST forgot-password mengirim notifikasi (Mail fake) dengan token benar.
  - Token tidak terungkap untuk email yang tidak terdaftar (pesan konsisten).
  - Reset berhasil → password baru bisa dipakai login.
  - Token invalid / expired ditolak.
- **`tests/Feature/User/UserTest.php` (update)**: email wajib & unik pada
  pembuatan user.
- `UserFactory` diperbarui (`email` => `fake()->unique()->safeEmail()`) agar
  seluruh suite tetap hijau.

## Konfigurasi ENV

Tidak ada variabel ENV baru — Resend sudah terkonfigurasi
(`MAIL_MAILER=resend`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`).

Dependency baru: tidak ada.

## Risiko / Catatan

- Email sementara (`@mail.local`) adalah domain dummy — user lama yang mau
  menerima email reset asli perlu email riil (diubah via DB/admin saat fitur
  edit user dibangun).
- `password_reset_tokens` di-rebuild; data token lama (jika ada) terhapus —
  tidak berdampak karena belum ada alur reset sebelumnya.
- Google OAuth ditunda; desain login (`email` unique) sudah kompatibel dengan
  penambahan OAuth nanti (`google_id` dsb.).
