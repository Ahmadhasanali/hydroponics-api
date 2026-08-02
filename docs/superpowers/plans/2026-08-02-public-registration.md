# Registrasi Publik + Verifikasi Email + Blokir Email Sementara — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan registrasi publik dengan verifikasi email wajib dan blokir email sementara, plus menonaktifkan forgot password untuk akun admin.

**Architecture:** Auth custom Laravel yang sudah ada diperluas: registrasi publik (auto-login → halaman verifikasi), verifikasi memakai komponen bawaan Laravel (`MustVerifyEmail`, `EmailVerificationRequest`, middleware `verified` — alias baru di `bootstrap/app.php`), anti-email-sementara via rule validasi custom `NoTempEmail` + config blocklist domain lokal, forgot password diblokir untuk `is_admin = true` di dua lapis (pengiriman link & endpoint reset).

**Tech Stack:** Laravel 13 (PHP 8.5), Laravel Sail, Blade + Tailwind v4 (glassmorphism dark, accent `#ffce54`), Resend (mail), PHPUnit 12, Pint.

**Spec:** `docs/superpowers/specs/2026-08-02-public-registration-design.md`

## Global Constraints

- Semua perintah artisan/phpunit/pint lewat Sail: `vendor/bin/sail artisan ...`, `vendor/bin/sail bin pint --dirty --format agent`.
- TDD: tulis test gagal → jalankan → implementasi minimal → test hijau → commit.
- Test wajib: `vendor/bin/sail artisan test --compact --filter=<NamaTest>`; seluruh suite: `vendor/bin/sail artisan test --compact`.
- Jangan hapus test yang ada. Semua test PHPUnit (bukan Pest). Gunakan `LazilyRefreshDatabase`.
- Tanpa dependency composer baru; tanpa variabel ENV baru.
- Auth custom (tanpa Fortify/Breeze). Copy UI Bahasa Indonesia. Style view auth: glassmorphism dark (`#ffce54` accent), mengikuti `resources/views/auth/login.blade.php`.
- Setiap tugas harus commit dengan pesan `feat:` / `test:` / `refactor:` yang deskriptif.
- **Deviasi dari spec (disetujui):** `UserFactory` default `email_verified_at => now()` (verified) — bukan null — agar seluruh test existing yang login+dashboard tetap hijau; state `unverified()` disediakan untuk test verifikasi. Registrasi publik tetap menghasilkan user unverified (controller tidak mengisi kolom).

## File Structure

**Dibuat:**
- `database/migrations/2026_08_02_000001_add_email_verified_at_to_users_table.php`
- `config/disposable-email-domains.php`
- `app/Rules/NoTempEmail.php`
- `tests/Unit/Rules/NoTempEmailTest.php`
- `app/Console/Commands/SyncDisposableEmailDomains.php`
- `app/Http/Controllers/RegistrationController.php`
- `app/Http/Requests/Auth/RegisterRequest.php`
- `tests/Feature/Auth/RegistrationTest.php`
- `app/Http/Controllers/EmailVerificationController.php`
- `app/Notifications/VerifyEmailNotification.php`
- `resources/views/auth/register.blade.php`
- `resources/views/auth/verify.blade.php`
- `resources/views/email/verify-email.blade.php`
- `tests/Feature/Auth/EmailVerificationTest.php`

**Dimodifikasi:**
- `app/Models/User.php`
- `database/factories/UserFactory.php`
- `app/Http/Controllers/UserController.php`
- `tests/Feature/User/UserTest.php`
- `bootstrap/app.php`
- `app/Http/Controllers/PasswordResetController.php`
- `tests/Feature/Auth/PasswordResetTest.php`
- `routes/auth.php`
- `routes/farm.php`, `routes/monitoring.php`, `routes/reports.php`, `routes/profile.php`, `routes/pwa.php`, `routes/chat.php`, `routes/admin.php` (tambah middleware `verified`)
- `resources/views/auth/login.blade.php`
- `tests/Feature/Auth/LoginTest.php` (jika perlu, hanya bila test rusak oleh middleware `verified`)

**Graf dependensi (urutan eksekusi wajib):**
- Wave 1 (paralel — tidak ada file yang sama): Task 1, Task 2, Task 3, Task 4
- Wave 2: Task 5 (butuh Task 1, Task 2)
- Wave 3: Task 6 (butuh Task 1, Task 5)
- Wave 4: Task 7 (integrasi)

---

### Task 1: Migrasi `email_verified_at` + User model + factory + pembuatan user admin

**Files:**
- Create: `database/migrations/2026_08_02_000001_add_email_verified_at_to_users_table.php`
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Http/Controllers/UserController.php`, `tests/Feature/User/UserTest.php`

**Interfaces:**
- Consumes: `users.email_verified_at` (belum ada — ditambah di sini), `is_admin` (sudah ada).
- Produces: `User implements MustVerifyEmail` (trait bawaan: `hasVerifiedEmail()`, `markEmailAsVerified()`, `sendEmailVerificationNotification()`), `UserFactory::verified()` & `UserFactory::unverified()` states. Kolom `users.email_verified_at` tersedia.

- [ ] **Step 1: Tulis test yang gagal — factory states & admin store verified**

Tambah ke `tests/Feature/User/UserTest.php`:

```php
public function test_admin_store_creates_verified_user(): void
{
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('user.store'), [
        'name' => 'Petani Baru',
        'email' => 'petani@example.com',
    ]);

    $response->assertRedirect(route('user.index'));
    $this->assertNotNull(User::where('email', 'petani@example.com')->first()->email_verified_at);
}

public function test_factory_has_verified_and_unverified_states(): void
{
    $this->assertNotNull(User::factory()->create()->email_verified_at);
    $this->assertNull(User::factory()->unverified()->create()->email_verified_at);
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/User/UserTest.php`
Expected: FAIL — kolom `email_verified_at` tidak ada / method `unverified()` tidak ada.

- [ ] **Step 3: Buat migration**

`vendor/bin/sail artisan make:migration add_email_verified_at_to_users_table --table=users --no-interaction`

Isi `up()`:

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->timestamp('email_verified_at')->nullable();
    });

    DB::table('users')->update(['email_verified_at' => now()]);
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('email_verified_at');
    });
}
```

`DB` sudah di-import otomatis oleh `make:migration` (blok `use Illuminate\Support\Facades\DB;` — tambahkan jika tidak ada).

- [ ] **Step 4: Update `User` model — implement `MustVerifyEmail`**

```php
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use MustVerifyEmail;
```

- [ ] **Step 5: Update `UserFactory` — default verified + state**

Ganti blok `unverified()` yang ter-komentar (baris 36-45) menjadi:

```php
/**
 * Indicate that the model's email address should be verified.
 */
public function verified(): static
{
    return $this->state(fn (array $attributes) => [
        'email_verified_at' => now(),
    ]);
}

/**
 * Indicate that the model's email address should be unverified.
 */
public function unverified(): static
{
    return $this->state(fn (array $attributes) => [
        'email_verified_at' => null,
    ]);
}
```

Tambahkan `'email_verified_at' => now(),` ke `definition()`.

- [ ] **Step 6: Update `UserController::store`**

```php
$user = User::query()->create($fields);
$user->markEmailAsVerified();
```

- [ ] **Step 7: Jalankan test, pastikan hijau**

Run: `vendor/bin/sail artisan test --compact tests/Feature/User/UserTest.php`
Expected: PASS (2 test baru + test existing).

- [ ] **Step 8: Jalankan seluruh suite auth agar tak ada yang rusak**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Auth`
Expected: PASS.

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add database/migrations/2026_08_02_000001_add_email_verified_at_to_users_table.php app/Models/User.php database/factories/UserFactory.php app/Http/Controllers/UserController.php tests/Feature/User/UserTest.php
git commit -m "feat: kolom email_verified_at + User MustVerifyEmail + verified/unverified factory states"
```

---

### Task 2: Blocklist domain + rule `NoTempEmail`

**Files:**
- Create: `config/disposable-email-domains.php`, `app/Rules/NoTempEmail.php`, `tests/Unit/Rules/NoTempEmailTest.php`

**Interfaces:**
- Consumes: tidak ada.
- Produces: `App\Rules\NoTempEmail` (object ValidationRule, pesan id), `config('disposable-email-domains')` = `array<string>` domain lowercase.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Unit/Rules/NoTempEmailTest.php`:

```php
<?php

namespace Tests\Unit\Rules;

use App\Rules\NoTempEmail;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NoTempEmailTest extends TestCase
{
    #[DataProvider('validEmails')]
    public function test_valid_emails_pass(string $email): void
    {
        $validator = Validator::make(['email' => $email], ['email' => [new NoTempEmail]]);

        $this->assertFalse($validator->fails(), "{$email} seharusnya lolos");
    }

    #[DataProvider('disposableEmails')]
    public function test_disposable_emails_fail(string $email): void
    {
        $validator = Validator::make(['email' => $email], ['email' => [new NoTempEmail]]);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Email sementara (temporary email) tidak diizinkan. Gunakan alamat email permanen.',
            $validator->errors()->first('email'),
        );
    }

    public static function validEmails(): array
    {
        return [
            ['petani@example.com'],
            ['user@gmail.com'],
            ['orang@yahoo.co.id'],
            ['ALI@Example.COM'],
        ];
    }

    public static function disposableEmails(): array
    {
        return [
            ['user@temp-mail.org'],
            ['user@guerrillamail.com'],
            ['user@yopmail.com'],
            ['user@MAILINATOR.com'],
            ['user@10minutemail.com'],
        ];
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/sail artisan test --compact tests/Unit/Rules/NoTempEmailTest.php`
Expected: FAIL — `App\Rules\NoTempEmail` tidak ditemukan.

- [ ] **Step 3: Buat config blocklist**

`config/disposable-email-domains.php` (seed awal — Task 3 akan mengisi list lengkap via sync):

```php
<?php

return [
    '10minutemail.com',
    'dispostable.com',
    'guerrillamail.com',
    'guerrillamailblock.com',
    'mailinator.com',
    'maildrop.cc',
    'throwaway.email',
    'tempmail.org',
    'temp-mail.org',
    'temp-mail.io',
    'trashmail.com',
    'yopmail.com',
];
```

- [ ] **Step 4: Buat rule `NoTempEmail`**

`vendor/bin/sail artisan make:rule NoTempEmail --no-interaction`, lalu isi:

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class NoTempEmail implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = Str::lower(Str::after((string) $value, '@'));
        $blocklist = array_flip(config('disposable-email-domains', []));

        if (isset($blocklist[$domain])) {
            $fail('Email sementara (temporary email) tidak diizinkan. Gunakan alamat email permanen.');
        }
    }
}
```

- [ ] **Step 5: Jalankan test, pastikan hijau**

Run: `vendor/bin/sail artisan test --compact tests/Unit/Rules/NoTempEmailTest.php`
Expected: PASS (semua data provider).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add config/disposable-email-domains.php app/Rules/NoTempEmail.php tests/Unit/Rules/NoTempEmailTest.php
git commit -m "feat: rule NoTempEmail + config blocklist domain email sementara"
```

---

### Task 3: Command sync blocklist + scheduler

**Files:**
- Create: `app/Console/Commands/SyncDisposableEmailDomains.php`
- Modify: `bootstrap/app.php`

**Interfaces:**
- Consumes: `config/disposable-email-domains.php` (Task 2).
- Produces: `php artisan app:sync-disposable-email-domains --path=<optional>` menulis ulang config blocklist; jadwal 6 bulanan di `bootstrap/app.php`.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/Commands/SyncDisposableEmailDomainsTest.php`:

```php
<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SyncDisposableEmailDomainsTest extends TestCase
{
    public function test_command_downloads_and_writes_blocklist(): void
    {
        Http::fake([
            'raw.githubusercontent.com/*' => Http::response("# comment\nMailinator.com\n\nyopmail.com\nguerrillamail.com\n"),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'blocklist').'.php';

        $this->artisan('app:sync-disposable-email-domains', ['--path' => $path])
            ->expectsOutputToContain('3')
            ->assertExitCode(0);

        $contents = File::get($path);
        $this->assertStringContainsString('mailinator.com', $contents);
        $this->assertStringContainsString('yopmail.com', $contents);
        $this->assertStringContainsString('guerrillamail.com', $contents);
        $this->assertStringNotContainsString('comment', $contents);

        File::delete($path);
    }

    public function test_command_fails_when_download_fails(): void
    {
        Http::fake([
            'raw.githubusercontent.com/*' => Http::response('error', 500),
        ]);

        $this->artisan('app:sync-disposable-email-domains')
            ->assertExitCode(1);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Commands/SyncDisposableEmailDomainsTest.php`
Expected: FAIL — command tidak ada.

- [ ] **Step 3: Buat command**

`vendor/bin/sail artisan make:command SyncDisposableEmailDomains --no-interaction`, lalu isi:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class SyncDisposableEmailDomains extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:sync-disposable-email-domains {--path= : Path file config output (default config/disposable-email-domains.php)}';

    /**
     * The console command description.
     */
    protected $description = 'Download daftar domain email sementara terbaru dan tulis ke config.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $url = 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/master/disposable_email_blocklist.conf';

        $response = Http::timeout(30)->get($url);

        if ($response->failed()) {
            $this->error('Gagal mengambil daftar domain email sementara.');

            return self::FAILURE;
        }

        $domains = collect(explode("\n", $response->body()))
            ->map(fn (string $line): string => strtolower(trim($line)))
            ->filter(fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#') && ! str_contains($line, ' '))
            ->unique()
            ->sort()
            ->values();

        $path = $this->option('path') ?: config_path('disposable-email-domains.php');
        $contents = "<?php\n\nreturn ".var_export($domains->all(), true).";\n";
        File::put($path, $contents);

        $this->info('Daftar domain email sementara diperbarui: '.$domains->count().' domain.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Daftarkan schedule 6 bulanan di `bootstrap/app.php`**

Di blok `withSchedule`, tambahkan baris:

```php
$schedule->command('app:sync-disposable-email-domains')->everySixMonths();
```

Verifikasi method ada: `vendor/bin/sail artisan schedule:list` — jika `everySixMonths` tidak tersedia (Laravel < 12.9), ganti dengan `->twiceYearly()`.

- [ ] **Step 5: Jalankan test, pastikan hijau**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Commands/SyncDisposableEmailDomainsTest.php`
Expected: PASS.

- [ ] **Step 6: Jalankan sync sekali (isi config penuh) + pastikan suite hijau**

```bash
vendor/bin/sail artisan app:sync-disposable-email-domains
vendor/bin/sail artisan test --compact tests/Unit/Rules/NoTempEmailTest.php
```

Expected: output jumlah domain (>30.000), rule test tetap PASS.

- [ ] **Step 7: Pint + commit (termasuk config yang terisi penuh)**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Console/Commands/SyncDisposableEmailDomains.php bootstrap/app.php config/disposable-email-domains.php tests/Feature/Commands/SyncDisposableEmailDomainsTest.php
git commit -m "feat: command sync disposable email domains + schedule 6 bulanan"
```

---

### Task 4: Forgot password diblokir untuk admin

**Files:**
- Modify: `app/Http/Controllers/PasswordResetController.php`, `tests/Feature/Auth/PasswordResetTest.php`

**Interfaces:**
- Consumes: `users.is_admin`, `User` model.
- Produces: `PasswordResetController::sendResetLinkEmail` & `reset` yang menolak akun admin (pesan generik).

- [ ] **Step 1: Tulis test yang gagal**

Tambah ke `tests/Feature/Auth/PasswordResetTest.php`:

```php
public function test_forgot_password_does_not_send_link_for_admin(): void
{
    Notification::fake();
    $admin = User::factory()->admin()->create(['email' => 'admin@mail.local']);

    $response = $this->post(route('password.email'), ['email' => 'admin@mail.local']);

    $response->assertSessionHas('status');
    Notification::assertNothingSent();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'admin@mail.local']);
}

public function test_admin_token_cannot_reset_password(): void
{
    $admin = User::factory()->admin()->create([
        'email' => 'admin@mail.local',
        'password' => Hash::make('old-password'),
    ]);
    $token = Password::broker()->createToken($admin);

    $response = $this->post(route('password.store'), [
        'token' => $token,
        'email' => 'admin@mail.local',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->assertTrue(Auth::attempt(['email' => 'admin@mail.local', 'password' => 'old-password']));
    $this->assertDatabaseHas('password_reset_tokens', ['email' => 'admin@mail.local']);
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Auth/PasswordResetTest.php`
Expected: FAIL — admin tetap menerima link / bisa reset.

- [ ] **Step 3: Update `sendResetLinkEmail`**

```php
public function sendResetLinkEmail(ForgotPasswordRequest $request): RedirectResponse
{
    $user = User::query()->where('email', $request->string('email'))->first();

    if ($user === null || ! $user->is_admin) {
        Password::broker()->sendResetLink($request->only('email'));
    }

    return back()->with('status', __('Jika email terdaftar, kami telah mengirim link reset password ke email Anda.'));
}
```

Tambah `use App\Models\User;` di header.

- [ ] **Step 4: Update `reset` (defense in depth)**

```php
public function reset(ResetPasswordRequest $request): RedirectResponse
{
    $user = User::query()->where('email', $request->string('email'))->first();

    if ($user !== null && $user->is_admin) {
        return back()->withErrors(['email' => __('Link reset password tidak valid atau sudah kedaluwarsa.')]);
    }

    $status = Password::broker()->reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user, $password): void {
            $user->password = $password;
            $user->save();
            event(new PasswordReset($user));
            Auth::login($user);
        }
    );

    return $status === Password::PASSWORD_RESET
        ? redirect()->route('dashboard')->with('status', __('Password berhasil direset.'))
        : back()->withErrors(['email' => __('Link reset password tidak valid atau sudah kedaluwarsa.')]);
}
```

- [ ] **Step 5: Jalankan seluruh test password reset, pastikan hijau**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Auth/PasswordResetTest.php`
Expected: PASS (11 test — 2 baru + 9 lama).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Http/Controllers/PasswordResetController.php tests/Feature/Auth/PasswordResetTest.php
git commit -m "feat: blokir forgot password untuk akun admin (link & endpoint reset)"
```

---

### Task 5: Registrasi publik

**Files:**
- Create: `app/Http/Controllers/RegistrationController.php`, `app/Http/Requests/Auth/RegisterRequest.php`, `resources/views/auth/register.blade.php`, `tests/Feature/Auth/RegistrationTest.php`
- Modify: `routes/auth.php`, `resources/views/auth/login.blade.php`

**Interfaces:**
- Consumes: `User` + `MustVerifyEmail` (Task 1), `App\Rules\NoTempEmail` (Task 2), route `verification.notice` (dibuat Task 6 — redirect ke nama route; test Task 5 mengecek redirect ke URL `/email/verify`).

Catatan: `redirect()->route('verification.notice')` mengarah ke route yang akan dibuat Task 6. Task 6 wajib berjalan sebelum seluruh suite dijalankan (Task 7). Untuk test Task 5, gunakan `assertRedirect('/email/verify')` (path literal, tidak bergantung nama route).

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/Auth/RegistrationTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee('Daftar');
    }

    public function test_new_user_can_register_and_is_redirected_to_verification(): void
    {
        Notification::fake();

        $response = $this->post(route('register'), [
            'name' => 'Petani Baru',
            'email' => 'petani@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/email/verify');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'petani@example.com',
            'email_verified_at' => null,
        ]);
        Notification::assertSentTo(
            User::where('email', 'petani@example.com')->first(),
            \Illuminate\Auth\Notifications\VerifyEmail::class,
        );
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'petani@example.com']);

        $response = $this->post(route('register'), [
            'name' => 'Petani Baru',
            'email' => 'petani@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_rejects_disposable_email(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Petani Baru',
            'email' => 'user@temp-mail.org',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_requires_confirmed_password(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Petani Baru',
            'email' => 'petani@example.com',
            'password' => 'password123',
            'password_confirmation' => 'beda-password',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Auth/RegistrationTest.php`
Expected: FAIL — route `register` tidak ada.

- [ ] **Step 3: Buat `RegisterRequest`**

`vendor/bin/sail artisan make:request Auth/RegisterRequest --no-interaction`, lalu isi:

```php
<?php

namespace App\Http\Requests\Auth;

use App\Rules\NoTempEmail;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email', new NoTempEmail],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * Get the custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }
}
```

- [ ] **Step 4: Buat `RegistrationController`**

`vendor/bin/sail artisan make:controller RegistrationController --no-interaction`, lalu isi:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    /**
     * Show the registration form.
     */
    public function showRegisterForm(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        $user->sendEmailVerificationNotification();

        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}
```

- [ ] **Step 5: Tambah route register di `routes/auth.php`**

Di dalam group `guest` (setelah route login):

```php
Route::get('/register', [RegistrationController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [RegistrationController::class, 'register'])->middleware('throttle:5,1');
```

- [ ] **Step 6: Buat view `auth/register.blade.php`**

Salin struktur & gaya `auth/login.blade.php` (glassmorphism, icon droplet, accent `#ffce54`), ubah: judul "Buat Akun", subtitle "Daftar untuk mengelola sistem hidroponik Anda", field: Nama (`text`, autocomplete `name`), Email (`email`, `autocomplete="email"`), Password (`password`, `autocomplete="new-password"`), Konfirmasi Password (`password`, `autocomplete="new-password"`). Tombol submit "Daftar". Di bawah form:

```html
<p class="mt-6 text-center text-sm text-slate-300">
    Sudah punya akun?
    <a href="{{ route('login') }}" class="font-semibold text-[#ffce54] transition hover:text-[#f0b830]">Masuk</a>
</p>
```

- [ ] **Step 7: Tambah link "Daftar" di `auth/login.blade.php`**

Di bawah `</form>`:

```html
<p class="mt-6 text-center text-sm text-slate-300">
    Belum punya akun?
    <a href="{{ route('register') }}" class="font-semibold text-[#ffce54] transition hover:text-[#f0b830]">Daftar sekarang</a>
</p>
```

- [ ] **Step 8: Jalankan test, pastikan hijau**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Auth/RegistrationTest.php`
Expected: PASS (5 test).

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Http/Controllers/RegistrationController.php app/Http/Requests/Auth/RegisterRequest.php resources/views/auth/register.blade.php resources/views/auth/login.blade.php routes/auth.php tests/Feature/Auth/RegistrationTest.php
git commit -m "feat: registrasi publik dengan validasi email & blokir email sementara"
```

---

### Task 6: Verifikasi email + middleware `verified`

**Files:**
- Create: `app/Http/Controllers/EmailVerificationController.php`, `app/Notifications/VerifyEmailNotification.php`, `resources/views/auth/verify.blade.php`, `resources/views/email/verify-email.blade.php`, `tests/Feature/Auth/EmailVerificationTest.php`
- Modify: `app/Models/User.php` (override `sendEmailVerificationNotification`), `routes/auth.php`, `bootstrap/app.php` (alias `verified`), `routes/farm.php`, `routes/monitoring.php`, `routes/reports.php`, `routes/profile.php`, `routes/pwa.php`, `routes/chat.php`, `routes/admin.php`, `tests/Feature/Auth/RegistrationTest.php` (update assertion notifikasi)

**Interfaces:**
- Consumes: `User implements MustVerifyEmail` (Task 1), route register (Task 5).
- Produces: route `verification.notice`, `verification.verify`, `verification.send`; `App\Notifications\VerifyEmailNotification`; middleware alias `verified`.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/Auth/EmailVerificationTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unverified_user_is_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_verification_notice_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertOk();
        $response->assertSee('Verifikasi Email');
    }

    public function test_user_can_verify_email_with_valid_hash(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'petani@example.com']);

        $response = $this->actingAs($user)->get(route('verification.verify', [
            'id' => $user->id,
            'hash' => sha1('petani@example.com'),
        ]));

        $response->assertRedirect(route('dashboard'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_user_cannot_verify_email_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('verification.verify', [
            'id' => $user->id,
            'hash' => 'hash-salah',
        ]));

        $response->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verification_link_can_be_resent(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->post(route('verification.send'));

        $response->assertRedirect();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_verified_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Auth/EmailVerificationTest.php`
Expected: FAIL — route `verification.*` tidak ada, alias `verified` belum terdaftar.

- [ ] **Step 3: Verifikasi alias middleware `verified` (default, fallback kustom)**

Alias `verified` → `EnsureEmailIsVerified` sudah terdaftar **secara default**
oleh framework di Laravel 11+ — bukan kustom. Setelah route dibuat di Step 4,
cek:

Run: `vendor/bin/sail artisan route:list --path=email`
Expected: kolom middleware route `verification.verify` menampilkan `verified`
dan `signed` **tanpa error "Class not found"**. Jika middleware `verified`
ternyata tidak ter-resolve (Laravel versi lebih lama dari 11), baru daftarkan
di `bootstrap/app.php`:

```php
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'superadmin' => EnsureSuperAdmin::class,
        'verified' => EnsureEmailIsVerified::class,
    ]);
})
```

- [ ] **Step 4: Tambah route verifikasi di `routes/auth.php`**

Di luar group `guest`, setelah group guest (route baru, butuh `auth`):

```php
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', function () {
        return view('auth.verify');
    })->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', EmailVerificationRequest::class)
        ->middleware('signed')
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});
```

Dengan import di atas file:

```php
use App\Http\Controllers\EmailVerificationController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
```

- [ ] **Step 5: Buat `EmailVerificationController`**

`vendor/bin/sail artisan make:controller EmailVerificationController --no-interaction`, lalu isi:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Resend the email verification notification.
     */
    public function send(Request $request): RedirectResponse
    {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', __('Link verifikasi telah dikirim ulang.'));
    }
}
```

- [ ] **Step 6: Buat `VerifyEmailNotification` + override di `User`**

`app/Notifications/VerifyEmailNotification.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends BaseVerifyEmail
{
    use Queueable;

    /**
     * Get the verify email notification mail representation.
     */
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verifikasi Email Anda')
            ->view('email.verify-email', [
                'url' => $url,
                'name' => $notifiable->name,
                'expire' => config('auth.verification.expire', 60),
            ]);
    }
}
```

Di `app/Models/User.php`, tambahkan override:

```php
use App\Notifications\VerifyEmailNotification;

/**
 * Send the email verification notification.
 */
public function sendEmailVerificationNotification(): void
{
    $this->notify(new VerifyEmailNotification);
}
```

- [ ] **Step 7: Buat template email `email/verify-email.blade.php`**

Salin struktur `resources/views/email/reset-password.blade.php`, ubah:
- Judul: "Verifikasi Email Anda"
- Body: "Halo {{ $name }},<br><br>Klik tombol di bawah untuk memverifikasi alamat email Anda dan mengaktifkan akun. Link ini berlaku selama {{ $expire }} menit."
- Tombol: `href="{{ $url }}"` teks "Verifikasi Email"
- Footer: "Jika Anda tidak membuat akun ini, abaikan email ini. — Kita Tumbuh"

- [ ] **Step 8: Buat view `auth/verify.blade.php`**

Gaya glassmorphism sama dengan login. Struktur:

```blade
@extends('layouts.auth')

@section('title', 'Verifikasi Email')

@section('content')
<div class="relative flex min-h-screen items-center justify-center px-4 py-10">
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -left-20 top-[-6rem] h-96 w-96 rounded-full bg-[#ffce54]/10 blur-3xl"></div>
        <div class="absolute bottom-[-8rem] right-[-4rem] h-[28rem] w-[28rem] rounded-full bg-[#cbe273]/10 blur-3xl"></div>
    </div>

    <div class="relative z-10 w-full max-w-md rounded-[1.75rem] border border-white/10 bg-white/10 p-8 shadow-2xl shadow-black/30 backdrop-blur-2xl sm:p-10">
        <div class="text-center">
            <div class="mx-auto inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-[#ffce54] text-2xl text-[#1a1c1e] shadow-lg shadow-[#ffce54]/20">
                <i class="bi bi-envelope-check"></i>
            </div>
            <h1 class="mt-6 text-3xl font-semibold tracking-tight text-white">Verifikasi Email</h1>
            <p class="mt-3 text-sm leading-6 text-slate-300">
                Kami telah mengirim link verifikasi ke email Anda. Klik link tersebut untuk mengaktifkan akun sebelum mengakses dashboard.
            </p>
        </div>

        @if (session('status'))
            <div class="mt-8 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100" role="alert">
                {{ session('status') }}
            </div>
        @endif

        <form action="{{ route('verification.send') }}" method="POST" class="mt-8">
            @csrf
            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-[#ffce54] px-4 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm shadow-[#ffce54]/20 transition hover:bg-[#f0b830] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/30">
                <i class="bi bi-envelope-arrow-up"></i>
                Kirim ulang link verifikasi
            </button>
        </form>

        <form action="{{ route('logout') }}" method="POST" class="mt-4">
            @csrf
            <button type="submit" class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white/70 transition hover:bg-white/10">
                Keluar
            </button>
        </form>
    </div>
</div>
@endsection
```

- [ ] **Step 9: Tambah middleware `verified` ke route ber-auth**

- `routes/auth.php`: route `home`, `dashboard`, `dashboard.switch-farm` → ubah `->middleware('auth')` menjadi `->middleware(['auth', 'verified'])`. Route `logout` tetap `auth` saja.
- `routes/farm.php`: `Route::group(['middleware' => ['auth', 'verified'], 'prefix' => 'farm', ...`
- `routes/monitoring.php`, `routes/reports.php`, `routes/pwa.php`, `routes/chat.php` (kedua group), `routes/profile.php`, `routes/admin.php`: tambah `'verified'` ke array middleware (`['auth', 'verified']` untuk admin: `['auth', 'verified', 'superadmin']`).

- [ ] **Step 10: Update assertion notifikasi di `RegistrationTest`**

Di `test_new_user_can_register_and_is_redirected_to_verification`:

```php
Notification::assertSentTo(
    User::where('email', 'petani@example.com')->first(),
    \App\Notifications\VerifyEmailNotification::class,
);
```

- [ ] **Step 11: Jalankan test, pastikan hijau**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Auth`
Expected: PASS — seluruh test auth (Registration, EmailVerification, Login, PasswordReset).

- [ ] **Step 12: Jalankan seluruh suite, perbaiki test yang rusak oleh middleware `verified`**

Run: `vendor/bin/sail artisan test --compact`
Jika ada test di luar `tests/Feature/Auth` yang login lalu akses route terproteksi dengan factory user **default** (yang sekarang verified) → tetap hijau. Jika ada test yang memakai `unverified()`/user non-verified lalu akses dashboard, perbaiki dengan menandai user verified. Jangan hapus test.

- [ ] **Step 13: Pint + commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Http/Controllers/EmailVerificationController.php app/Notifications/VerifyEmailNotification.php app/Models/User.php resources/views/auth/verify.blade.php resources/views/email/verify-email.blade.php routes/auth.php routes/farm.php routes/monitoring.php routes/reports.php routes/profile.php routes/pwa.php routes/chat.php routes/admin.php bootstrap/app.php tests/Feature/Auth/EmailVerificationTest.php tests/Feature/Auth/RegistrationTest.php
git commit -m "feat: verifikasi email wajib + middleware verified pada route terproteksi"
```

---

### Task 7: Integrasi & verifikasi akhir

**Files:** tidak ada perubahan kode.

- [ ] **Step 1: Jalankan seluruh test suite**

Run: `vendor/bin/sail artisan test --compact`
Expected: PASS seluruhnya.

- [ ] **Step 2: Verifikasi route list**

Run: `vendor/bin/sail artisan route:list --except-vendor`
Expected: `register` (GET/POST), `verification.notice`, `verification.verify`, `verification.send` ada; route terproteksi memakai middleware `verified`.

- [ ] **Step 3: Verifikasi scheduler**

Run: `vendor/bin/sail artisan schedule:list`
Expected: `app:sync-disposable-email-domains` muncul (6 bulanan / twice yearly).

- [ ] **Step 4: Smoke test manual di browser** (`vendor/bin/sail up -d`, buka `http://localhost`):
  1. `/register` tampil, link "Daftar" muncul di `/login`.
  2. Daftar dengan email temp (mis. `x@temp-mail.org`) → error, tidak ada user dibuat.
  3. Daftar dengan email riil → redirect halaman verifikasi, email masuk (mailpit/log mail).
  4. Klik link verifikasi → dashboard terbuka.
  5. Coba akses dashboard sebelum verifikasi (user baru lain) → redirect ke halaman verifikasi.
  6. Login admin → `POST /forgot-password` → pesan sukses, email TIDAK terkirim.
  7. `vendor/bin/sail artisan app:sync-disposable-email-domains` → config terisi penuh.

- [ ] **Step 5: Commit akhir (jika ada perbaikan dari smoke test)**

```bash
git add -A
git commit -m "fix: perbaikan hasil smoke test integrasi"
```

---

## Catatan Eksekusi (parallel agentic)

- **Wave 1 — jalankan Task 1, 2, 3, 4 secara paralel** (agent terpisah; tidak ada file yang sama). Gunakan worktree/git branch terpisah per agent bila perlu, atau satu working tree dengan urutan commit yang aman (masing-masing task hanya menyentuh file miliknya).
- **Wave 2 — Task 5** (menyentuh `routes/auth.php`).
- **Wave 3 — Task 6** (menyentuh `routes/auth.php` + file route lain; WAJIB setelah Task 5).
- **Wave 4 — Task 7** integrasi.
- Task 5 dan Task 6 **tidak boleh paralel** karena sama-sama mengedit `routes/auth.php`.
- Setiap task: jalankan test-nya sendiri dulu (`--filter`), lalu suite yang lebih luas di step akhir task.
