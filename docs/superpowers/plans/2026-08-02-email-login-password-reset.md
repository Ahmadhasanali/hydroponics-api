# Login Email + Forgot Password — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti login berbasis `username` menjadi `email` + password, tambah alur forgot password (kirim link reset via Resend).

**Architecture:** Perluas `AuthController` yang ada dengan kolom `email` baru di tabel `users` (backfill placeholder untuk user lama), rebuild tabel `password_reset_tokens` ke schema standar agar `Password::broker()` bawaan Laravel bekerja, lalu tambah `PasswordResetController`, notifikasi mail kustom, dan dua view baru (forgot & reset) bergaya glassmorphism yang sama dengan halaman login.

**Tech Stack:** Laravel 13, PHP 8.5, PostgreSQL, Laravel Sail, Tailwind CSS 4, Resend (mailer). Tanpa dependency baru.

## Global Constraints

- PHP 8.5 / Laravel 13; semua perintah artisan/composer/node WAJIB via `vendor/bin/sail` (contoh: `vendor/bin/sail artisan test ...`).
- Tidak boleh menambah dependency baru.
- Setiap perubahan PHP diakhiri dengan `vendor/bin/sail bin pint --dirty --format agent`.
- Test memakai PHPUnit (bukan Pest); gunakan `LazilyRefreshDatabase`. Test DB memakai `MAIL_MAILER=array`.
- Jangan menghapus/mengurangi test yang sudah ada tanpa approval.
- String error/status memakai Bahasa Indonesia **inline** (tidak ada folder `lang/`), mengikuti konvensi kode (`__('Email atau password salah.')`).
- Model memakai atribut `#[Fillable([...])]`; cast password `hashed` sudah ada.
- Migrasi untuk tabel `users` diletakkan di `database/migrations/User/` (dimuat oleh `loadMigrationsFrom` di `AppServiceProvider`).
- Ikuti pola commit pesan konvensional (`feat:`, `test:`, `docs:`) sesuai history repo.

---

### Task 1: Kolom `email` di `users` + model + factory + seeder

**Files:**
- Create: `database/migrations/User/2026_08_02_000000_add_email_to_users_table.php`
- Modify: `app/Models/User.php` (tambah `email` di `#[Fillable]`)
- Modify: `database/factories/UserFactory.php`
- Modify: `database/seeders/User/UserAdminSeeder.php`
- Modify: `database/seeders/User/UserSeeder.php`
- Modify: `tests/Feature/Database/UserMigrationTest.php` (tambah `email` ke daftar kolom)
- Create: `tests/Feature/Database/EmailMigrationTest.php`

**Interfaces:**
- Produces: kolom `users.email` (`unique`, `not null`); `User::factory()` selalu menghasilkan `email`; seeder superadmin ber-email `superadmin@mail.local`, hasan ber-email `hasan@mail.local`. Semua task berikut bergantung pada kolom `email`.

- [ ] **Step 1: Tulis test gagal** — `tests/Feature/Database/EmailMigrationTest.php`

```php
<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmailMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_users_table_has_not_nullable_email_column_with_unique_index(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'email'));

        $columns = Schema::getColumns('users');
        $email = collect($columns)->firstWhere('name', 'email');
        $this->assertNotNull($email, 'email column not found');
        $this->assertFalse($email['nullable'], 'email column should not be nullable');

        $indexes = Schema::getIndexes('users');
        $this->assertContains('users_email_unique', array_column($indexes, 'name'));
    }

    public function test_user_factory_generates_unique_email(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->assertNotNull($first->email);
        $this->assertNotSame($first->email, $second->email);
    }
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Database/EmailMigrationTest.php`
Expected: GAGAL — `Schema::hasColumn('users', 'email')` false (kolom belum ada).

- [ ] **Step 3: Tambah `email` ke `UserMigrationTest` yang sudah ada**

Dalam `tests/Feature/Database/UserMigrationTest.php`, tambahkan `'email',` ke array `$expectedColumns`.

- [ ] **Step 4: Tulis migrasi** — `database/migrations/User/2026_08_02_000000_add_email_to_users_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->after('name');
        });

        DB::table('users')
            ->whereNull('email')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['email' => Str::lower($user->name).'@mail.local']);
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->unique()->after('name')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn('email');
        });
    }
};
```

- [ ] **Step 5: Update model, factory, seeder**

`app/Models/User.php` — ubah atribut fillable:
```php
#[Fillable(['name', 'email', 'password'])]
```

`database/factories/UserFactory.php` — tambah ke `definition()`:
```php
'email' => fake()->unique()->safeEmail(),
```

`database/seeders/User/UserAdminSeeder.php` — tambah ke `User::create([...])`:
```php
'email' => 'superadmin@mail.local',
```

`database/seeders/User/UserSeeder.php` — tambah ke array atribut `firstOrCreate`:
```php
'email' => 'hasan@mail.local',
```

- [ ] **Step 6: Jalankan test untuk verifikasi lulus**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Database/EmailMigrationTest.php tests/Feature/Database/UserMigrationTest.php`
Expected: PASS semua.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add database/migrations/User/2026_08_02_000000_add_email_to_users_table.php app/Models/User.php database/factories/UserFactory.php database/seeders/User/UserAdminSeeder.php database/seeders/User/UserSeeder.php tests/Feature/Database/EmailMigrationTest.php tests/Feature/Database/UserMigrationTest.php
git commit -m "feat: add unique email column to users with backfill"
```

---

### Task 2: Rebuild tabel `password_reset_tokens` ke schema standar

**Files:**
- Create: `database/migrations/User/2026_08_02_000001_rebuild_password_reset_tokens_table.php`
- Create: `tests/Feature/Database/PasswordResetTokensMigrationTest.php`

**Interfaces:**
- Produces: tabel `password_reset_tokens` ber-schema `email` (PK), `token`, `created_at`. `Password::broker()` (Task 4) membaca/menulis tabel ini.

- [ ] **Step 1: Tulis test gagal** — `tests/Feature/Database/PasswordResetTokensMigrationTest.php`

```php
<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordResetTokensMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_password_reset_tokens_table_uses_email_primary_key(): void
    {
        $this->assertTrue(Schema::hasTable('password_reset_tokens'));

        $columns = Schema::getColumns('password_reset_tokens');
        $names = array_column($columns, 'name');

        $this->assertContains('email', $names);
        $this->assertContains('token', $names);
        $this->assertContains('created_at', $names);
        $this->assertNotContains('user_id', $names);
    }
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Database/PasswordResetTokensMigrationTest.php`
Expected: GAGAL — kolom `user_id` masih ada (schema lama).

- [ ] **Step 3: Tulis migrasi rebuild** — `database/migrations/User/2026_08_02_000001_rebuild_password_reset_tokens_table.php`

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
        Schema::dropIfExists('password_reset_tokens');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
```

- [ ] **Step 4: Jalankan test untuk verifikasi lulus**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Database/PasswordResetTokensMigrationTest.php`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add database/migrations/User/2026_08_02_000001_rebuild_password_reset_tokens_table.php tests/Feature/Database/PasswordResetTokensMigrationTest.php
git commit -m "feat: rebuild password_reset_tokens to standard laravel schema"
```

---

### Task 3: Login berbasis email (request, controller, rate limiter, view)

**Files:**
- Modify: `app/Http/Requests/Auth/LoginRequest.php`
- Modify: `app/Http/Controllers/AuthController.php`
- Modify: `app/Providers/AppServiceProvider.php:64` (rate limiter `username` → `email`)
- Modify: `resources/views/auth/login.blade.php`
- Modify: `tests/Feature/Auth/LoginTest.php`

**Interfaces:**
- Consumes: kolom `users.email` (Task 1).
- Produces: `POST /login` menerima `email` + `password`; error login pada key `email`.

- [ ] **Step 1: Tulis/update test gagal** — `tests/Feature/Auth/LoginTest.php`

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Selamat Datang');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'email' => 'ali@mail.local',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'ali@mail.local',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'ali@mail.local',
            'password' => Hash::make('password123'),
        ]);

        $this->post(route('login'), [
            'email' => 'ali@mail.local',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_unknown_email(): void
    {
        $this->post(route('login'), [
            'email' => 'nobody@mail.local',
            'password' => 'password123',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_name_instead_of_email(): void
    {
        User::factory()->create([
            'name' => 'aliusername',
            'email' => 'ali@mail.local',
            'password' => Hash::make('password123'),
        ]);

        $this->post(route('login'), [
            'email' => 'aliusername',
            'password' => 'password123',
        ]);

        $this->assertGuest();
    }

    public function test_login_redirects_back_with_errors_on_failure(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'nobody@mail.local',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $response->assertRedirect(url()->previous());
    }

    public function test_email_and_password_are_required(): void
    {
        $response = $this->post(route('login'), []);

        $response->assertSessionHasErrors(['email', 'password']);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Auth/LoginTest.php`
Expected: GAGAL — request `email` tidak valid / login tidak berhasil (login masih memakai `username`).

- [ ] **Step 3: Update `LoginRequest`** — `app/Http/Requests/Auth/LoginRequest.php`

```php
public function rules(): array
{
    return [
        'email' => ['required', 'string', 'email'],
        'password' => ['required', 'string'],
    ];
}

public function messages(): array
{
    return [
        'email.required' => 'Email wajib diisi.',
        'email.email' => 'Format email tidak valid.',
        'password.required' => 'Password wajib diisi.',
    ];
}
```

- [ ] **Step 4: Update `AuthController::login`** — `app/Http/Controllers/AuthController.php`

```php
$credentials = [
    'email' => $request->string('email')->toString(),
    'password' => $request->string('password')->toString(),
];

if (! Auth::attempt($credentials, $request->boolean('remember'))) {
    return back()
        ->withInput($request->only('email', 'remember'))
        ->withErrors(['email' => __('Email atau password salah.')]);
}
```

- [ ] **Step 5: Update rate limiter** — `app/Providers/AppServiceProvider.php` baris `RateLimiter::for('login', ...)`

Ubah `$request->input('username')` → `$request->input('email')`.

- [ ] **Step 6: Update view login** — `resources/views/auth/login.blade.php`

Ganti blok input username (label "Username", `name="username"`, ikon `bi-person-fill`, `autocomplete="username"`) menjadi:

```blade
<div>
    <label for="email" class="block text-sm font-semibold text-white/80">Email</label>
    <div class="relative mt-2">
        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-white/40">
            <i class="bi bi-envelope-fill"></i>
        </span>
        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus autocomplete="email"
            class="block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 pl-11 text-sm text-white placeholder-slate-400 transition focus:border-[#ffce54]/50 focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
            placeholder="Masukkan email">
    </div>
    @error('email')
        <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
    @enderror
</div>
```

(Jangan ubah bagian lain. Tombol "Lupa kata sandi?" ditambahkan di Task 6.)

- [ ] **Step 7: Jalankan test untuk verifikasi lulus**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Auth/LoginTest.php`
Expected: PASS semua.

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Http/Requests/Auth/LoginRequest.php app/Http/Controllers/AuthController.php app/Providers/AppServiceProvider.php resources/views/auth/login.blade.php tests/Feature/Auth/LoginTest.php
git commit -m "feat: switch login to email-based authentication"
```

---

### Task 4: Forgot password backend (route, controller, form request, views minimal)

**Files:**
- Create: `app/Http/Requests/Auth/ForgotPasswordRequest.php`
- Create: `app/Http/Requests/Auth/ResetPasswordRequest.php`
- Create: `app/Http/Controllers/PasswordResetController.php`
- Modify: `routes/auth.php`
- Create: `resources/views/auth/forgot-password.blade.php`
- Create: `resources/views/auth/reset-password.blade.php`
- Create: `tests/Feature/Auth/PasswordResetTest.php`

**Interfaces:**
- Consumes: tabel `password_reset_tokens` (Task 2), kolom `users.email` (Task 1).
- Produces: route bernama `password.request`, `password.email`, `password.reset`, `password.store`. `User` mengirim notifikasi `Illuminate\Auth\Notifications\ResetPassword` (bawaan) untuk sementara; Task 5 menggantinya.

- [ ] **Step 1: Tulis test gagal** — `tests/Feature/Auth/PasswordResetTest.php`

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
        $response->assertSee('Lupa Kata Sandi');
    }

    public function test_password_reset_link_is_sent_for_registered_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ali@mail.local']);

        $response = $this->post(route('password.email'), ['email' => 'ali@mail.local']);

        $response->assertSessionHas('status');
        Notification::assertSentTo($user, fn ($notification) => true);
    }

    public function test_password_reset_link_shows_same_status_for_unknown_email(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), ['email' => 'nobody@mail.local']);

        $response->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $response = $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]));

        $response->assertOk();
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'ali@mail.local',
            'password' => Hash::make('old-password'),
        ]);
        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'ali@mail.local',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Auth::attempt(['email' => 'ali@mail.local', 'password' => 'new-password']));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'ali@mail.local']);
    }

    public function test_password_can_not_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'ali@mail.local',
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->post(route('password.store'), [
            'token' => 'invalid-token',
            'email' => 'ali@mail.local',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertFalse(Auth::attempt(['email' => 'ali@mail.local', 'password' => 'new-password']));
    }

    public function test_password_can_not_be_reset_with_expired_token(): void
    {
        $user = User::factory()->create([
            'email' => 'ali@mail.local',
            'password' => Hash::make('old-password'),
        ]);
        $token = Password::broker()->createToken($user);
        DB::table('password_reset_tokens')
            ->where('email', 'ali@mail.local')
            ->update(['created_at' => now()->subMinutes(61)]);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'ali@mail.local',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_reset_requires_email_and_password(): void
    {
        $response = $this->post(route('password.store'), [
            'token' => 'token',
        ]);

        $response->assertSessionHasErrors(['email', 'password']);
    }
}
```

Tambahan imports yang dibutuhkan: `use Illuminate\Support\Facades\Auth;` di bagian atas file.

- [ ] **Step 2: Jalankan test untuk verifikasi gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Auth/PasswordResetTest.php`
Expected: GAGAL — route tidak terdefinisi.

- [ ] **Step 3: Buat form request** — `app/Http/Requests/Auth/ForgotPasswordRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
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
            'email' => ['required', 'email'],
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
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ];
    }
}
```

- [ ] **Step 4: Buat form request** — `app/Http/Requests/Auth/ResetPasswordRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
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
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)],
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
            'token.required' => 'Token reset password tidak valid.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }
}
```

- [ ] **Step 5: Buat controller** — `app/Http/Controllers/PasswordResetController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    /**
     * Show the form to request a password reset link.
     */
    public function showLinkRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a password reset link to the given email address.
     */
    public function sendResetLinkEmail(ForgotPasswordRequest $request): RedirectResponse
    {
        Password::broker()->sendResetLink($request->only('email'));

        return back()->with('status', __('Jika email terdaftar, kami telah mengirim link reset password ke email Anda.'));
    }

    /**
     * Show the form to reset the password for the given token.
     */
    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    /**
     * Reset the user's password.
     */
    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
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
}
```

- [ ] **Step 6: Tambah route** — `routes/auth.php`, di dalam blok `Route::middleware('guest')->group(...)`

```php
Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->middleware('throttle:6,1')->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1')->name('password.store');
```

Tambahkan import: `use App\Http\Controllers\PasswordResetController;`.

- [ ] **Step 7: Buat view minimal** — `resources/views/auth/forgot-password.blade.php`

```blade
@extends('layouts.auth')

@section('title', 'Lupa Kata Sandi')

@section('content')
<div class="relative flex min-h-screen items-center justify-center px-4 py-10">
    <div class="relative z-10 w-full max-w-md rounded-[1.75rem] border border-white/10 bg-white/10 p-8 shadow-2xl shadow-black/30 backdrop-blur-2xl sm:p-10">
        <h1 class="text-center text-3xl font-semibold tracking-tight text-white">Lupa Kata Sandi</h1>
        <p class="mt-3 text-center text-sm leading-6 text-slate-300">Masukkan email Anda untuk menerima link reset password.</p>

        @if (session('status'))
            <div class="mt-8 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="mt-8 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">Silakan periksa kembali input Anda.</div>
        @endif

        <form action="{{ route('password.email') }}" method="POST" class="mt-8 space-y-5" novalidate>
            @csrf
            <div>
                <label for="email" class="block text-sm font-semibold text-white/80">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                    class="mt-2 block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white placeholder-slate-400 transition focus:border-[#ffce54]/50 focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                    placeholder="Masukkan email">
                @error('email')
                    <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="w-full rounded-2xl bg-[#ffce54] px-4 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm shadow-[#ffce54]/20 transition hover:bg-[#f0b830] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/30">
                Kirim Link Reset
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-300">
            <a href="{{ route('login') }}" class="font-semibold text-[#ffce54] hover:text-[#f0b830]">Kembali ke login</a>
        </p>
    </div>
</div>
@endsection
```

- [ ] **Step 8: Buat view minimal** — `resources/views/auth/reset-password.blade.php`

```blade
@extends('layouts.auth')

@section('title', 'Reset Password')

@section('content')
<div class="relative flex min-h-screen items-center justify-center px-4 py-10">
    <div class="relative z-10 w-full max-w-md rounded-[1.75rem] border border-white/10 bg-white/10 p-8 shadow-2xl shadow-black/30 backdrop-blur-2xl sm:p-10">
        <h1 class="text-center text-3xl font-semibold tracking-tight text-white">Reset Password</h1>
        <p class="mt-3 text-center text-sm leading-6 text-slate-300">Buat kata sandi baru untuk akun Anda.</p>

        @if ($errors->any())
            <div class="mt-8 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">Silakan periksa kembali input Anda.</div>
        @endif

        <form action="{{ route('password.store') }}" method="POST" class="mt-8 space-y-5" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            <div>
                <label for="password" class="block text-sm font-semibold text-white/80">Kata Sandi Baru</label>
                <input type="password" name="password" id="password" required autocomplete="new-password"
                    class="mt-2 block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white placeholder-slate-400 transition focus:border-[#ffce54]/50 focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                    placeholder="Minimal 8 karakter">
                @error('password')
                    <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-white/80">Konfirmasi Kata Sandi</label>
                <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password"
                    class="mt-2 block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white placeholder-slate-400 transition focus:border-[#ffce54]/50 focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                    placeholder="Ulangi kata sandi baru">
            </div>

            <button type="submit"
                class="w-full rounded-2xl bg-[#ffce54] px-4 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm shadow-[#ffce54]/20 transition hover:bg-[#f0b830] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/30">
                Simpan Kata Sandi
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-300">
            <a href="{{ route('login') }}" class="font-semibold text-[#ffce54] hover:text-[#f0b830]">Kembali ke login</a>
        </p>
    </div>
</div>
@endsection
```

- [ ] **Step 9: Jalankan test untuk verifikasi lulus**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Auth/PasswordResetTest.php`
Expected: PASS semua.

- [ ] **Step 10: Pint + commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Http/Requests/Auth/ForgotPasswordRequest.php app/Http/Requests/Auth/ResetPasswordRequest.php app/Http/Controllers/PasswordResetController.php routes/auth.php resources/views/auth/forgot-password.blade.php resources/views/auth/reset-password.blade.php tests/Feature/Auth/PasswordResetTest.php
git commit -m "feat: add forgot password flow with reset link email"
```

---

### Task 5: Notifikasi reset kustom + template email

**Files:**
- Create: `app/Notifications/ResetPasswordNotification.php`
- Create: `resources/views/email/reset-password.blade.php`
- Modify: `app/Models/User.php` (tambah `sendPasswordResetNotification`)
- Modify: `tests/Feature/Auth/PasswordResetTest.php` (ganti assertion notifikasi ke kelas kustom + cek URL di email)

**Interfaces:**
- Consumes: route `password.reset` (Task 4).
- Produces: `App\Notifications\ResetPasswordNotification` (extend `Illuminate\Auth\Notifications\ResetPassword`); `User::sendPasswordResetNotification(string $token)`; view `email.reset-password`.

- [ ] **Step 1: Update test — assertion notifikasi kustom** — `tests/Feature/Auth/PasswordResetTest.php`

Tambah imports:
```php
use App\Notifications\ResetPasswordNotification;
```

Ganti test `test_password_reset_link_is_sent_for_registered_email` menjadi:

```php
public function test_password_reset_link_is_sent_for_registered_email(): void
{
    Notification::fake();
    $user = User::factory()->create(['email' => 'ali@mail.local']);

    $response = $this->post(route('password.email'), ['email' => 'ali@mail.local']);

    $response->assertSessionHas('status');
    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        return Password::broker()->tokenExists($user, $notification->token);
    });
}
```

Tambahkan test baru untuk isi email:

```php
public function test_reset_email_contains_valid_reset_url(): void
{
    $user = User::factory()->create(['email' => 'ali@mail.local']);
    $token = Password::broker()->createToken($user);

    $notification = new ResetPasswordNotification($token);
    $mail = $notification->toMail($user);
    $html = $mail->render();

    $this->assertStringContainsString(
        route('password.reset', ['token' => $token, 'email' => 'ali@mail.local']),
        $html
    );
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Auth/PasswordResetTest.php`
Expected: GAGAL — `ResetPasswordNotification` tidak ditemukan.

- [ ] **Step 3: Buat notifikasi** — `app/Notifications/ResetPasswordNotification.php`

```php
<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    use Queueable;

    /**
     * Get the reset password notification mail representation.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('Reset Kata Sandi Anda')
            ->view('email.reset-password', [
                'url' => $url,
                'name' => $notifiable->name,
                'expire' => config('auth.passwords.users.expire', 60),
            ]);
    }
}
```

- [ ] **Step 4: Tambah method di `User` model** — `app/Models/User.php`

```php
/**
 * Send the password reset notification.
 */
public function sendPasswordResetNotification(string $token): void
{
    $this->notify(new ResetPasswordNotification($token));
}
```

Tambah import: `use App\Notifications\ResetPasswordNotification;`.

- [ ] **Step 5: Buat template email** — `resources/views/email/reset-password.blade.php`

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Kata Sandi</title>
</head>
<body style="margin:0;padding:0;background-color:#0d0e10;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#0d0e10;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background-color:#1a1c1e;border-radius:24px;padding:32px;border:1px solid rgba(255,255,255,0.1);">
                    <tr>
                        <td align="center" style="font-size:13px;letter-spacing:0.05em;text-transform:uppercase;color:#ffce54;font-weight:bold;">Hydroponic Farm Management</td>
                    </tr>
                    <tr>
                        <td style="padding-top:24px;color:#ffffff;font-size:22px;font-weight:bold;line-height:1.4;">Reset Kata Sandi Anda</td>
                    </tr>
                    <tr>
                        <td style="padding-top:12px;color:#cbd5e1;font-size:14px;line-height:1.6;">
                            Halo {{ $name }},<br><br>
                            Kami menerima permintaan untuk mereset kata sandi akun Anda. Klik tombol di bawah untuk membuat kata sandi baru. Link ini berlaku selama {{ $expire }} menit.
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding-top:28px;">
                            <a href="{{ $url }}" style="display:inline-block;background-color:#ffce54;color:#1a1c1e;text-decoration:none;font-weight:bold;font-size:14px;padding:14px 32px;border-radius:14px;">Reset Kata Sandi</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top:28px;color:#94a3b8;font-size:13px;line-height:1.6;">
                            Jika Anda tidak meminta reset kata sandi, abaikan email ini.<br><br>
                            — Kita Tumbuh
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
```

- [ ] **Step 6: Jalankan test untuk verifikasi lulus**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Auth/PasswordResetTest.php`
Expected: PASS semua.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Notifications/ResetPasswordNotification.php resources/views/email/reset-password.blade.php app/Models/User.php tests/Feature/Auth/PasswordResetTest.php
git commit -m "feat: add custom password reset notification with branded email"
```

---

### Task 6: Link "Lupa kata sandi?" di login + polish view

**Files:**
- Modify: `resources/views/auth/login.blade.php` (tambah link forgot password)
- Modify: `tests/Feature/Auth/LoginTest.php` (assert link ada)

**Interfaces:**
- Consumes: route `password.request` (Task 4).

- [ ] **Step 1: Update test — login memuat link forgot password** — `tests/Feature/Auth/LoginTest.php`

Tambah test:

```php
public function test_login_screen_links_to_forgot_password(): void
{
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee(route('password.request'));
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Auth/LoginTest.php --filter=test_login_screen_links_to_forgot_password`
Expected: GAGAL — link belum ada di view.

- [ ] **Step 3: Tambah link di view login** — `resources/views/auth/login.blade.php`

Ubah blok baris "Ingat saya" menjadi:

```blade
<div class="flex items-center justify-between">
    <label class="flex items-center gap-2 text-sm text-white/70">
        <input type="checkbox" name="remember" id="remember"
            class="h-4 w-4 rounded border-white/20 bg-white/5 text-[#ffce54] focus:ring-[#ffce54]/30"
            {{ old('remember') ? 'checked' : '' }}>
        Ingat saya
    </label>

    <a href="{{ route('password.request') }}" class="text-sm font-semibold text-[#ffce54] transition hover:text-[#f0b830]">
        Lupa kata sandi?
    </a>
</div>
```

- [ ] **Step 4: Jalankan test untuk verifikasi lulus**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Auth/LoginTest.php`
Expected: PASS semua.

- [ ] **Step 5: Commit**

```bash
git add resources/views/auth/login.blade.php tests/Feature/Auth/LoginTest.php
git commit -m "feat: add forgot password link to login page"
```

---

### Task 7: Pembuatan user admin wajib email + full suite + pint

**Files:**
- Modify: `app/Http/Requests/User/StoreUserRequest.php`
- Modify: `app/Http/Controllers/UserController.php`
- Modify: `tests/Feature/User/UserTest.php`

**Interfaces:**
- Consumes: kolom `users.email` (Task 1).
- Produces: `POST /users` (route `user.store`) menerima `name` + `email` wajib.

- [ ] **Step 1: Update test — email wajib & unik** — `tests/Feature/User/UserTest.php`

Tambah import: `use Illuminate\Support\Str;`

Update `test_admin_can_store_user` agar mengirim email:

```php
public function test_admin_can_store_user(): void
{
    $user = User::factory()->admin()->create();

    $this->actingAs($user);

    $response = $this->post(route('user.store'), [
        'name' => 'aliusername',
        'email' => 'ali@mail.local',
    ]);

    $this->assertDatabaseHas('users', [
        'name' => 'aliusername',
        'email' => 'ali@mail.local',
    ]);

    $response->assertRedirect(route('user.index'));
    $response->assertSessionHas('password');
    $this->assertNotNull(session('password'));
}
```

Tambah test baru:

```php
public function test_admin_store_user_requires_email(): void
{
    $user = User::factory()->admin()->create();

    $this->actingAs($user);

    $response = $this->post(route('user.store'), [
        'name' => 'aliusername',
    ]);

    $response->assertInvalid(['email']);
}

public function test_admin_store_user_rejects_duplicate_email(): void
{
    User::factory()->create(['email' => 'existing@mail.local']);
    $user = User::factory()->admin()->create();

    $this->actingAs($user);

    $response = $this->post(route('user.store'), [
        'name' => 'aliusername',
        'email' => 'existing@mail.local',
    ]);

    $response->assertInvalid(['email']);
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/User/UserTest.php`
Expected: GAGAL — email tidak wajib / tersimpan.

- [ ] **Step 3: Update `StoreUserRequest`** — `app/Http/Requests/User/StoreUserRequest.php`

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
    ];
}

public function messages(): array
{
    return [
        'email.required' => 'Email wajib diisi.',
        'email.email' => 'Format email tidak valid.',
        'email.unique' => 'Email sudah digunakan.',
    ];
}
```

- [ ] **Step 4: Update `UserController::store`** — `app/Http/Controllers/UserController.php`

```php
$fields = [
    'name' => $request->string('name')->toString(),
    'email' => $request->string('email')->toString(),
    'password' => Hash::make($randomChar),
];
```

- [ ] **Step 5: Jalankan test untuk verifikasi lulus**

Run: `vendor/bin/sail artisan test --compact tests/Feature/User/UserTest.php`
Expected: PASS semua.

- [ ] **Step 6: Jalankan seluruh suite + pint**

Run: `vendor/bin/sail artisan test --compact`
Expected: PASS semua (pastikan tidak ada test lain yang patah karena kolom email baru).
Jika ada test lain yang gagal karena asumsi `username` login / user tanpa email, perbaiki mengikuti pola Task 3/7, lalu jalankan ulang.

```bash
vendor/bin/sail bin pint --dirty --format agent
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/User/StoreUserRequest.php app/Http/Controllers/UserController.php tests/Feature/User/UserTest.php
git commit -m "feat: require email when creating users"
```

---

## Catatan Eksekusi

- Semua langkah memakai `vendor/bin/sail`; pastikan `vendor/bin/sail up -d` sedang berjalan sebelum menjalankan test.
- Test memakai `MAIL_MAILER=array` (dari `phpunit.xml`) sehingga email tidak benar-benar terkirim saat tes.
- Verifikasi manual (opsional, setelah Task 5): kirim link reset sungguhan dengan
  `vendor/bin/sail artisan tinker --execute 'Password::broker()->sendResetLink(["email" => "email-anda@domain.com"]);'`
  lalu cek inbox. (Resend aktif di `.env`: `MAIL_MAILER=resend`.)
