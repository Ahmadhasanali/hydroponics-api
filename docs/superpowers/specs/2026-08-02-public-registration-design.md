# Registrasi Publik + Verifikasi Email + Blokir Email Sementara — Design

> **Status:** Approved
> **Date:** 2026-08-02
> **Project:** Hydroponic Farm Management System (Laravel 13)

## Latar Belakang & Tujuan

Saat ini user **hanya bisa dibuat oleh admin** (`UserController::store` dengan
password random). Tidak ada jalur registrasi mandiri. Login email + forgot
password sudah berjalan (spec 2026-08-02-email-login-password-reset).

**Tujuan:**
1. Tambah **registrasi publik terbuka** (`/register`).
2. **Verifikasi email wajib** sebelum akses aplikasi (keputusan baru — spec
   password reset sebelumnya menyatakan verifikasi tidak diminta, kini berubah).
3. **Blokir email sementara/dummy** (temp-mail.org, guerrillamail.com, dll.)
   saat registrasi.

## Keputusan Kunci

- **Registrasi terbuka untuk publik.** Tidak ada kode undangan.
- **Verifikasi email wajib** via klik link (bukan OTP), memakai komponen
  bawaan Laravel (`MustVerifyEmail`, `EmailVerificationRequest`, middleware
  `verified`) di atas auth custom yang ada — pola yang sama dengan pemakaian
  `Password::broker()` pada spec sebelumnya. Tidak menggunakan Fortify/Breeze.
- **Anti-email-sementara via blocklist domain lokal** (strategi A):
  list domain temp dari repo publik `disposable-email-domains/disposable-email-domains`
  (~40k domain) dibundel sebagai `config/disposable-email-domains.php` + rule
  validasi custom `no_temp_email`. Tanpa API eksternal, gratis, offline.
  Verifikasi email wajib menjadi lapisan kedua.
- **Auto-login setelah registrasi** (pola standar Laravel), langsung diarahkan
  ke halaman verifikasi; akses aplikasi dicegat middleware `verified`.
- **User lama & user buatan admin dianggap terverifikasi** (admin menjamin):
  backfill `email_verified_at = now()` untuk semua user existing, dan
  `UserController::store` mengisi `email_verified_at` saat create.
- **Tanpa farm setelah registrasi** — dashboard sudah menangani user tanpa farm
  (tampil kosong). Farm dibuat/ditambahkan admin nanti.
- **Validasi anti-email-sementara hanya di jalur registrasi publik**.
- **Scheduler sync blocklist setiap 6 bulan** (command artisan
  `app:sync-disposable-email-domains` + schedule; Laravel 12+ punya
  `everySixMonths()`, fallback `twiceYearly()`).
- **Forgot password dinonaktifkan untuk akun admin** (`is_admin = true`):
  admin tidak bisa reset password mandiri via email — hardening terhadap
  kompromi email admin & mencegah tindakan sewenang-wenang. Hanya user biasa
  yang bisa reset mandiri.

## Lingkup

### In Scope
- Migrasi kolom `email_verified_at` (timestamp, nullable) + backfill user lama.
- Blocklist: `config/disposable-email-domains.php`, rule `NoTempEmail`
  (alias `no_temp_email`), command `app:sync-disposable-email-domains` + scheduler.
- Registrasi: routes, `RegistrationController`, `RegisterRequest`, view
  `auth/register.blade.php`.
- Verifikasi: routes bawaan Laravel (`/email/verify/{id}/{hash}`,
  `/email/verification-notification`), `User` implement `MustVerifyEmail`,
  middleware `verified` pada seluruh group route ber-auth (dashboard, farm,
  monitoring, reports, profile, chat).
- Notifikasi verifikasi: mail class + template blade ber-brand (pola sama
  dengan `ResetPasswordNotification`), dikirim via Resend.
- View halaman verifikasi (notice) + kirim ulang link.
- `UserController::store` → `email_verified_at = now()`.
- Forgot password: admin ditolak di pengiriman link **dan** di endpoint reset
  (defense in depth).
- Tests.

### Out of Scope / Deferred
- Google OAuth (sudah ditunda di spec sebelumnya).
- Verifikasi via OTP.
- Edit email di profil (email diubah via admin/DB).
- Anti-email-sementara di jalur pembuatan admin / jalur input email lain.
- Auto-send/scheduler pengiriman email verifikasi.
- Halaman admin "kelola user" (tetap stub).

## Arsitektur

```
┌──────────────────────────────────────────────────────────────┐
│  WEB (guest)                                                 │
│  GET  /register                         showRegisterForm     │
│  POST /register (throttle:5,1)         register              │
│  GET  /email/verify/{id}/{hash}        EmailVerificationRequest │
│  POST /email/verification-notification (throttle:6,1)        │
│                ┌──────────────┐                               │
│                │    users     │  config/disposable-           │
│                │ email_       │  email-domains.php            │
│                │ verified_at  │  (blocklist ~40k domain)      │
│                └──────────────┘                               │
│                                                               │
│  WEB (auth + verified)                                        │
│  dashboard, farm, monitoring, reports, profile, chat          │
│  └─ middleware: auth, verified                                │
└──────────────────────────────────────────────────────────────┘
```

## Detail Komponen

### 1. Migrasi `email_verified_at` di `users`
```php
$table->timestamp('email_verified_at')->nullable();
```
Setelah kolom ditambahkan, backfill: `UPDATE users SET email_verified_at = now()`.

### 2. Blocklist domain
- `config/disposable-email-domains.php` mengembalikan array string domain
  lowercase (contoh: `'temp-mail.org'`, `'guerrillamail.com'`).
- List dibundel dari repo publik `disposable-email-domains/disposable-email-domains`
  (public domain). File config ditulis oleh command sync (lihat #4) —
  di-commit sekali saat implementasi agar tidak ada dependency runtime.

### 3. Rule `NoTempEmail`
- `App\Rules\NoTempEmail implements ValidationRule`.
- Logika: ambil domain dari email (`Str::after($value, '@')`), lowercase,
  cek ke list yang di-`array_flip` (hash lookup O(1)).
- Pesan (id): *"Email sementara (temporary email) tidak diizinkan. Gunakan
  alamat email permanen."*

### 4. Command `app:sync-disposable-email-domains`
- `App\Console\Commands\SyncDisposableEmailDomains`: fetch list terbaru dari
  GitHub raw (URL list utama), sort + lowercase + de-duplicate, tulis ulang
  `config/disposable-email-domains.php`.
- Didaftarkan di `routes/console.php`: `Schedule::command(...)->everySixMonths()`
  (fallback `twiceYearly()`).
- Catatan: butuh cron `schedule:run` di server (perlu diverifikasi saat deploy;
  jika belum ada, didokumentasikan di Risiko/Catatan).

### 5. Routes (guest)
```php
Route::get('/register', [RegistrationController::class, 'showRegisterForm'])
    ->name('register');
Route::post('/register', [RegistrationController::class, 'register'])
    ->middleware('throttle:5,1');

Route::get('/email/verify/{id}/{hash}', EmailVerificationRequest::class)
    ->middleware(['auth', 'signed'])->name('verification.verify');
Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
    ->middleware(['auth', 'throttle:6,1'])->name('verification.send');
```

### 6. `RegistrationController` + `RegisterRequest`
- `RegisterRequest`:
  ```php
  'name'     => ['required', 'string', 'max:255'],
  'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email', new NoTempEmail],
  'password' => ['required', 'string', 'min:8', 'confirmed'],
  ```
  Pesan: "Nama wajib diisi.", "Email sudah terdaftar.", "Email sementara
  (temporary email) tidak diizinkan. Gunakan alamat email permanen.",
  "Kata sandi minimal 8 karakter."
- `register()`: validasi → `User::create([... 'email_verified_at' => null])`
  → `Auth::login($user)` → redirect `route('verification.notice')` + flash.

### 7. `User` model + middleware `verified`
- `User` implement `MustVerifyEmail`.
- Route ber-`auth` (dashboard, farm, monitoring, reports, profile, chat)
  ditambah `verified`.

### 8. Notifikasi verifikasi (Mail)
- `App\Notifications\VerifyEmailNotification` extends
  `Illuminate\Auth\Notifications\VerifyEmail`, override `toMail` memakai view
  blade kustom `email.verify-email.blade.php` (brand "Kita Tumbuh", tombol
  `route('verification.verify', [$notifiable->getKey(), sha1($notifiable->getEmailForVerification())])`
  dengan `expires` + `signed`).
- Dikirim otomatis saat registrasi via `$user->sendEmailVerificationNotification()`
  (bawaan `MustVerifyEmail`).

### 9. View baru
- `auth/register.blade.php`: form nama + email + password + konfirmasi,
  gaya glassmorphism sama dengan `auth/login.blade.php` (dark, accent `#ffce54`),
  link ke login.
- `auth/verify.blade.php` (notice): pesan "verifikasi email Anda", tombol
  kirim ulang (form POST `verification.send`), link logout.
- `email/verify-email.blade.php`: template mail ber-brand.

### 10. `UserController::store`
- Tambah `'email_verified_at' => now()` pada `$fields` saat create user admin.

### 11. `EmailVerificationController`
- Satu method `send(Request $request)`: kirim ulang notifikasi verifikasi
  (`$request->user()->sendEmailVerificationNotification()`), redirect back +
  flash sukses ("Link verifikasi telah dikirim ulang."). Dipasang pada route
  `verification.send` dengan middleware `auth` + `throttle:6,1`.

### 12. Forgot password — blokir admin (modifikasi `PasswordResetController`)
- **`sendResetLinkEmail`**: jika email yang diminta milik user dengan
  `is_admin = true`, link **tidak dikirim** — namun tetap redirect back dengan
  flash sukses generik yang sama ("Jika email terdaftar, link reset telah
  dikirim ke email Anda.") agar keberadaan akun admin tidak bocor
  (anti-enumeration).
- **`reset`** (defense in depth): token yang dibuat untuk akun admin tidak
  dapat dipakai — setelah `Password::broker()->reset(...)` berhasil, cek
  `$user->is_admin`; jika admin, batal (password tidak diubah) dan beri error
  validasi generik. Dengan ini token lama/bocor pun tidak berguna untuk admin.
- Jalur pemulihan admin yang lupa password: **reset manual via DB**
  (tinker/query langsung, di-set hash baru) oleh admin lain. Dicatat sebagai
  prosedur manual — tidak dibangun fitur baru.

## Alur Data

**Registrasi:**
1. User membuka `/register`, isi nama/email/password, submit `POST /register`.
2. `RegisterRequest` memvalidasi (termasuk `unique` + `NoTempEmail`).
3. User dibuat (`email_verified_at = null`), auto-login, redirect halaman
   verifikasi.
4. `sendEmailVerificationNotification()` mengirim mail ber-link signed
   (berlaku 60 menit) via Resend.
5. User klik link `GET /email/verify/{id}/{hash}` → `email_verified_at` diisi →
   redirect dashboard.

**Akses sebelum verifikasi:**
- User yang login tapi belum verifikasi diarahkan middleware `verified` ke
  halaman verifikasi; tombol "kirim ulang" memicu `POST
  /email/verification-notification`.

## Error Handling

- **Email duplikat**: pesan "Email sudah terdaftar." (standard — mencegah
  duplikasi lebih utama daripada anti-enumeration; tidak perlu pesan samar
  seperti forgot-password).
- **Email sementara**: ditolak rule `NoTempEmail` dengan pesan khusus.
- **Rate limit**: registrasi 5/menit/IP; kirim ulang verifikasi 6/menit.
- **Gagal kirim email verifikasi**: user tetap dibuat (tidak rollback),
  redirect halaman verifikasi; jalur pemulihan = tombol kirim ulang.
- **Link verifikasi invalid/expired**: pesan standar Laravel, user bisa
  minta kirim ulang.
- **Admin memakai forgot password**: pesan sukses generik, link tidak dikirim
  (anti-enumeration — keberadaan akun admin tidak bocor).
- **Admin memakai token reset (bocor/lama)**: ditolak di endpoint reset,
  password tidak diubah.
- **Email tidak valid secara format**: pesan validasi standar.

## Testing

- **`tests/Unit/Rules/NoTempEmailTest.php` (baru)**: domain normal lolos;
  domain temp (`temp-mail.org`, `guerrillamail.com`, `yopmail.com`) ditolak;
  casing domain dinormalisasi; format bukan email ditangani rule `email`
  terlebih dahulu.
- **`tests/Feature/Auth/RegistrationTest.php` (baru)**:
  - Halaman register dapat dirender.
  - Registrasi sukses → user dibuat (`email_verified_at` null) + auto-login +
    redirect halaman verifikasi + notifikasi terkirim (Mail fake).
  - Email duplikat ditolak.
  - Email temp ditolak (tidak ada user dibuat).
  - Password wajib konfirmasi; password pendek ditolak.
- **`tests/Feature/Auth/EmailVerificationTest.php` (baru)**:
  - Link verifikasi valid → `email_verified_at` terisi → dashboard diakses.
  - Link invalid (hash salah) ditolak.
  - Kirim ulang notifikasi (throttle).
  - User belum verifikasi tidak bisa akses dashboard (redirect halaman
    verifikasi).
- **`tests/Feature/User/UserTest.php` (update)**: user buatan admin langsung
  terverifikasi.
- **`tests/Feature/Auth/PasswordResetTest.php` (update)**:
  - Forgot password dengan email admin → pesan sukses generik **dan tidak ada
    notifikasi terkirim** (Mail fake: `assertNothingSent`).
  - Forgot password dengan email user biasa → notifikasi terkirim (tidak
    berubah dari perilaku sebelumnya).
  - Token yang dibuat untuk admin tidak bisa dipakai di endpoint reset
    (password tidak berubah, error validasi).
- **Migration test**: backfill `email_verified_at` untuk user lama.
- **Existing tests** (`LoginTest`, dsb.) tetap hijau; `UserFactory` sudah punya
  `email_verified_at => null` (default) — state `verified()` ditambah bila perlu.

## Konfigurasi ENV

Tidak ada variabel ENV baru. Link verifikasi memakai `APP_URL` (default Laravel).
`MAIL_MAILER=resend`, `RESEND_API_KEY`, `MAIL_FROM_*` sudah terkonfigurasi.

Dependency baru: tidak ada (blocklist dibundel sebagai config, bukan package).

## Risiko / Catatan

- **Scheduler butuh cron** `schedule:run` di server. Perlu diverifikasi pada
  setup deploy (`compose.yaml`/server) — jika belum ada, sync dijalankan manual
  sampai cron tersedia.
- **List usang di antara sync (6 bulan)**: layanan temp baru bisa lolos sampai
  sync berikutnya; dimitigasi verifikasi email wajib dan auto-login yang
  diarahkan ke halaman verifikasi (akses aplikasi tetap tertunda).
- **False positive**: sebagian daftar publik mencakup domain yang jarang dipakai
  orang Indonesia; dipilih repo yang paling terpelihara. Jika ditemukan domain
  sah yang terblokir, dihapus manual dari config.
- `email_verified_at` user lama di-backfill ke `now()` — user existing tidak
  terkunci.
- **Admin yang lupa password** tidak bisa reset mandiri; pemulihan manual via
  DB oleh admin lain. Prosedur terdokumentasi, tidak ada fitur baru.
