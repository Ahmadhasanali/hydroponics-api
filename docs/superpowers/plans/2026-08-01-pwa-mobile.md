# PWA Mobile (Installable + Push Notifikasi FCM) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menjadikan aplikasi installable PWA (manifest + service worker + fast-load), push notification FCM (pengingat harian + aktivitas anggota farm), bottom nav mobile, dan halaman Profil — tanpa deployment.

**Architecture:** Satu service worker tunggal (strategy `injectManifest` dari `vite-plugin-pwa`) yang menggabungkan precache Workbox DAN Firebase Messaging background (menghindari konflik dua SW). Backend memakai `kreait/firebase-php` (FCM HTTP v1 API) melalui `PushNotificationService`; token device disimpan di tabel `push_subscriptions`; trigger notifikasi lewat scheduler command + model observers + queue job. Navigasi mobile memakai bottom nav 4 item; hamburger dihapus di mobile; sidebar desktop-only; halaman Profil jadi hub link sekunder.

**Tech Stack:** Laravel 13 (PHP 8.5, Sail), Vite 8, `vite-plugin-pwa@^1.3` (mendukung Vite 8), `firebase@^12` (messaging), `kreait/firebase-php@^7` (FCM), PostgreSQL, PHPUnit 12 (kelas PHPUnit, bukan Pest).

## Global Constraints

- Working dir semua perintah: `Hydroponic-Farm-Management-System_Laravel`, lewat Sail: `./vendor/bin/sail ...`.
- Dependensi baru SUDAH disetujui di spec: `vite-plugin-pwa`, `firebase` (npm); `kreait/firebase-php` (composer).
- Nilai ENV FCM (`VITE_FIREBASE_*`, `FCM_SERVICE_ACCOUNT_JSON`) **belum tersedia** — user akan menyusulkan. Implementasi memakai placeholder di `.env.example`; test memakai mock/fake, tidak butuh nilai asli.
- Test ditulis sebagai kelas PHPUnit (bukan Pest). Jangan menghapus test yang ada.
- Setelah mengubah file PHP, jalankan `./vendor/bin/sail bin pint --dirty --format agent`.
- Jangan commit API key/secrets; hanya variabel di `.env.example`.
- `phpunit.xml` memakai `QUEUE_CONNECTION=sync` — job berjalan sinkron di test; observer baru tidak boleh merusak test existing (aman karena `sendToUser` early-return jika user tanpa subscription).
- Jangan jalankan `npm run build` / `php artisan migrate` terhadap DB produksi.

---

### Task 1: PushSubscription — migration, model, factory, relasi User

**Files:**
- Create: `database/migrations/2026_08_01_000001_create_push_subscriptions_table.php`
- Create: `app/Models/PushSubscription.php`
- Create: `database/factories/PushSubscriptionFactory.php`
- Modify: `app/Models/User.php` (tambah relasi `pushSubscriptions`)
- Test: `tests/Unit/Models/PushSubscriptionTest.php`

**Interfaces:**
- Consumes: — (task pertama)
- Produces:
  - `App\Models\PushSubscription` — fillable `['user_id','fcm_token','platform','device_info']`, relasi `user(): BelongsTo`.
  - `User::pushSubscriptions(): HasMany` → `PushSubscription`.
  - Factory `Database\Factories\PushSubscriptionFactory`.

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
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fcm_token')->unique();
            $table->string('platform')->default('android');
            $table->string('device_info')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
```

- [ ] **Step 2: Tulis model**

```php
<?php

namespace App\Models;

use Database\Factories\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fcm_token',
        'platform',
        'device_info',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 3: Tulis factory**

```php
<?php

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PushSubscriptionFactory extends Factory
{
    protected $model = PushSubscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'fcm_token' => fake()->unique()->regexify('[A-Za-z0-9:._-]{120,160}'),
            'platform' => 'android',
            'device_info' => fake()->optional()->userAgent(),
        ];
    }
}
```

- [ ] **Step 4: Tambah relasi di User.php**

Tambah `use Illuminate\Database\Eloquent\Relations\HasMany;` dan method:

```php
    /**
     * @return HasMany<PushSubscription,User>
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }
```

- [ ] **Step 5: Tulis test unit**

`tests/Unit/Models/PushSubscriptionTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_factory_creates_subscription(): void
    {
        $subscription = PushSubscription::factory()->create();

        $this->assertDatabaseHas('push_subscriptions', ['id' => $subscription->id]);
    }

    public function test_subscription_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $subscription = PushSubscription::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($subscription->user->is($user));
        $this->assertTrue($user->pushSubscriptions()->first()->is($subscription));
    }
}
```

- [ ] **Step 6: Jalankan test — verifikasi FAIL dulu (tabel belum ada)**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Models/PushSubscriptionTest.php`
Expected: FAIL (table `push_subscriptions` does not exist)

- [ ] **Step 7: Jalankan migration**

Run: `./vendor/bin/sail artisan migrate`

- [ ] **Step 8: Jalankan test — verifikasi PASS**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Models/PushSubscriptionTest.php`
Expected: PASS (2 tests)

- [ ] **Step 9: Pint + commit**

```bash
./vendor/bin/sail bin pint --dirty --format agent
git add database/migrations/2026_08_01_000001_create_push_subscriptions_table.php app/Models/PushSubscription.php app/Models/User.php database/factories/PushSubscriptionFactory.php tests/Unit/Models/PushSubscriptionTest.php
git commit -m "feat: add push subscription model, migration, factory"
```

---

### Task 2: Endpoint push-subscriptions (controller + routes + feature tests)

**Files:**
- Create: `app/Http/Controllers/PushSubscriptionController.php`
- Create: `routes/pwa.php`
- Modify: `routes/web.php` (require pwa.php)
- Test: `tests/Feature/PushSubscription/PushSubscriptionControllerTest.php`

**Interfaces:**
- Consumes: `PushSubscription` dari Task 1.
- Produces:
  - `POST /push-subscriptions` → `push-subscriptions.store` → JSON `{success: true}` (201/200).
  - `DELETE /push-subscriptions` → `push-subscriptions.destroy` → JSON `{success: true}`.
  - Request body `fcm_token` (required), `platform`, `device_info` (nullable).

- [ ] **Step 1: Tulis controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
            'device_info' => ['nullable', 'string', 'max:255'],
        ]);

        PushSubscription::updateOrCreate(
            ['fcm_token' => $validated['fcm_token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'] ?? 'android',
                'device_info' => $validated['device_info'] ?? null,
            ],
        );

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:255'],
        ]);

        PushSubscription::where('user_id', $request->user()->id)
            ->where('fcm_token', $validated['fcm_token'])
            ->delete();

        return response()->json(['success' => true]);
    }
}
```

- [ ] **Step 2: Tulis routes**

`routes/pwa.php`:

```php
<?php

use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])
        ->name('push-subscriptions.store');

    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])
        ->name('push-subscriptions.destroy');
});
```

- [ ] **Step 3: Register di web.php**

Tambah baris `require __DIR__.'/pwa.php';` setelah `require __DIR__.'/chat.php';`.

- [ ] **Step 4: Tulis feature test**

`tests/Feature/PushSubscription/PushSubscriptionControllerTest.php`:

```php
<?php

namespace Tests\Feature\PushSubscription;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PushSubscriptionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_store_token(): void
    {
        $this->postJson(route('push-subscriptions.store'), [
            'fcm_token' => 'token-abc',
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_store_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('push-subscriptions.store'), [
            'fcm_token' => 'token-abc',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'fcm_token' => 'token-abc',
            'platform' => 'android',
        ]);
    }

    public function test_fcm_token_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('push-subscriptions.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fcm_token');
    }

    public function test_authenticated_user_can_delete_own_token(): void
    {
        $user = User::factory()->create();
        $subscription = PushSubscription::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->deleteJson(route('push-subscriptions.destroy'), [
            'fcm_token' => $subscription->fcm_token,
        ])->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $subscription->id]);
    }

    public function test_user_cannot_delete_another_users_token(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $subscription = PushSubscription::factory()->create(['user_id' => $other->id]);

        $this->actingAs($owner)->deleteJson(route('push-subscriptions.destroy'), [
            'fcm_token' => $subscription->fcm_token,
        ])->assertOk();

        $this->assertDatabaseHas('push_subscriptions', ['id' => $subscription->id]);
    }
}
```

- [ ] **Step 5: Jalankan test — verifikasi FAIL dulu**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/PushSubscription/PushSubscriptionControllerTest.php`
Expected: FAIL (route belum ada)

- [ ] **Step 6: Jalankan test — verifikasi PASS**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/PushSubscription/PushSubscriptionControllerTest.php`
Expected: PASS (5 tests)

- [ ] **Step 7: Pint + commit**

```bash
./vendor/bin/sail bin pint --dirty --format agent
git add app/Http/Controllers/PushSubscriptionController.php routes/pwa.php routes/web.php tests/Feature/PushSubscription/PushSubscriptionControllerTest.php
git commit -m "feat: add push subscription endpoints"
```

---

### Task 3: PushNotificationService + integrasi FCM (kreait/firebase-php)

**Files:**
- Create: `config/fcm.php`
- Create: `app/Services/PushNotificationService.php`
- Modify: `app/Providers/AppServiceProvider.php` (binding singleton `Messaging`)
- Modify: `.env.example` (tambah `FCM_SERVICE_ACCOUNT_JSON`)
- Test: `tests/Unit/Services/PushNotificationServiceTest.php`

**Interfaces:**
- Consumes: `PushSubscription` + `User::pushSubscriptions()` (Task 1).
- Produces:
  - `PushNotificationService::sendToUser(User $user, string $title, string $body, ?string $url = null): void` — mengirim ke semua token user; hapus token `NotFound`; log error lain (tidak throw).
  - Binding container `Kreait\Firebase\Contract\Messaging` (singleton, lazy — hanya di-resolve saat ada token).

- [ ] **Step 1: Install package**

Run: `./vendor/bin/sail composer require kreait/firebase-php`
Verifikasi versi terinstal `^7`.

- [ ] **Step 2: Tulis config/fcm.php**

```php
<?php

return [
    'service_account_json' => env('FCM_SERVICE_ACCOUNT_JSON'),
];
```

- [ ] **Step 3: Binding Messaging di AppServiceProvider**

Tambahkan use statement:

```php
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
```

Ganti `register()` menjadi:

```php
    public function register(): void
    {
        $this->app->singleton(Messaging::class, function () {
            $serviceAccount = config('fcm.service_account_json');

            if (! $serviceAccount || ! is_file($serviceAccount)) {
                throw new RuntimeException('FCM belum dikonfigurasi: isi FCM_SERVICE_ACCOUNT_JSON di .env.');
            }

            return (new Factory)->withServiceAccount($serviceAccount)->createMessaging();
        });
    }
```

- [ ] **Step 4: Tulis PushNotificationService**

`app/Services/PushNotificationService.php`:

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class PushNotificationService
{
    public function __construct(private Messaging $messaging)
    {
    }

    public function sendToUser(User $user, string $title, string $body, ?string $url = null): void
    {
        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        foreach ($subscriptions as $subscription) {
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withToken($subscription->fcm_token);

            if ($url) {
                $message = $message->withData(['url' => $url]);
            }

            try {
                $this->messaging->send($message);
            } catch (NotFound) {
                $subscription->delete();
            } catch (MessagingException $e) {
                Log::warning("FCM gagal kirim ke token #{$subscription->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
```

- [ ] **Step 5: Tambah ENV ke .env.example**

```env
FCM_SERVICE_ACCOUNT_JSON=
```

- [ ] **Step 6: Tulis unit test (mock Messaging)**

`tests/Unit/Services/PushNotificationServiceTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Mockery;
use Tests\TestCase;

class PushNotificationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_skips_when_user_has_no_subscriptions(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldNotReceive('send');

        $service = new PushNotificationService($messaging);
        $user = User::factory()->create();

        $service->sendToUser($user, 'Judul', 'Isi');
        $this->addToAssertionCount(1);
    }

    public function test_sends_to_each_subscription(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->twice();

        $service = new PushNotificationService($messaging);
        $user = User::factory()->create();
        PushSubscription::factory()->count(2)->create(['user_id' => $user->id]);

        $service->sendToUser($user, 'Judul', 'Isi', '/dashboard');
    }

    public function test_deletes_unregistered_token(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->andThrow(new NotFound('Token tidak terdaftar'));

        $service = new PushNotificationService($messaging);
        $user = User::factory()->create();
        $subscription = PushSubscription::factory()->create(['user_id' => $user->id]);

        $service->sendToUser($user, 'Judul', 'Isi');

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $subscription->id]);
    }

    public function test_logs_other_errors_and_keeps_token(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->andThrow(new InvalidMessage('Pesan tidak valid'));

        $service = new PushNotificationService($messaging);
        $user = User::factory()->create();
        $subscription = PushSubscription::factory()->create(['user_id' => $user->id]);

        $service->sendToUser($user, 'Judul', 'Isi');

        $this->assertDatabaseHas('push_subscriptions', ['id' => $subscription->id]);
    }
}
```

> Catatan implementasi: gunakan `use Kreait\Firebase\Exception\Messaging\InvalidMessage;` (contoh exception `MessagingException` non-NotFound). Verifikasi nama kelas exception persis dari `vendor/kreait/firebase-php/src/Firebase/Exception/Messaging/` versi terinstal — konstruktor biasanya `(string $message)`.

- [ ] **Step 7: Jalankan test — verifikasi PASS**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Services/PushNotificationServiceTest.php`
Expected: PASS (4 tests). Jika `NotFound` konstruktor berbeda, sesuaikan argumen sesuai API package.

- [ ] **Step 8: Pint + commit**

```bash
./vendor/bin/sail bin pint --dirty --format agent
git add config/fcm.php app/Services/PushNotificationService.php app/Providers/AppServiceProvider.php .env.example tests/Unit/Services/PushNotificationServiceTest.php composer.json composer.lock
git commit -m "feat: add FCM push notification service"
```

---

### Task 4: Command pengingat harian + scheduler

**Files:**
- Create: `app/Console/Commands/NotifyDailyMonitoring.php`
- Modify: `bootstrap/app.php` (jadwal `dailyAt`)
- Modify: `config/app.php` (tambah `daily_reminder_hour`)
- Modify: `.env.example` (tambah `DAILY_REMINDER_HOUR`)
- Test: `tests/Feature/Commands/NotifyDailyMonitoringTest.php`

**Interfaces:**
- Consumes: `PushNotificationService::sendToUser()` (Task 3).
- Produces: command artisan `notify:daily-monitoring` — exit code 0; idempoten per hari via cache key `daily_monitoring_reminder:Y-m-d`.

- [ ] **Step 1: Tulis command**

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class NotifyDailyMonitoring extends Command
{
    protected $signature = 'notify:daily-monitoring';

    protected $description = 'Mengirim pengingat monitoring harian ke pengguna dengan perangkat terdaftar';

    public function handle(PushNotificationService $push): int
    {
        $cacheKey = 'daily_monitoring_reminder:'.now()->toDateString();

        if (Cache::has($cacheKey)) {
            $this->info('Pengingat sudah terkirim hari ini.');

            return self::SUCCESS;
        }

        $recipients = User::whereHas('pushSubscriptions')->get();

        foreach ($recipients as $user) {
            $push->sendToUser(
                $user,
                'Waktunya Monitoring',
                'Catat PPM & pH tangki hari ini',
                route('daily-monitoring.create'),
            );
        }

        Cache::put($cacheKey, true, now()->endOfDay());
        $this->info("Pengingat dikirim ke {$recipients->count()} pengguna.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Register jadwal di bootstrap/app.php**

Dalam `->withSchedule(function (Schedule $schedule): void { ... })`, tambahkan baris kedua:

```php
        $schedule->command('notify:daily-monitoring')->dailyAt(config('app.daily_reminder_hour', '08:00'));
```

- [ ] **Step 3: Tambah config/app.php**

Tambah di array `return [ ... ];` level atas:

```php
    'daily_reminder_hour' => env('DAILY_REMINDER_HOUR', '08:00'),
```

- [ ] **Step 4: Tambah .env.example**

```env
DAILY_REMINDER_HOUR=08:00
```

- [ ] **Step 5: Tulis feature test**

`tests/Feature/Commands/NotifyDailyMonitoringTest.php`:

```php
<?php

namespace Tests\Feature\Commands;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class NotifyDailyMonitoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sends_reminder_to_users_with_subscriptions_only(): void
    {
        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')->once()->with(
            Mockery::type(User::class),
            'Waktunya Monitoring',
            Mockery::type('string'),
            Mockery::type('string'),
        );
        $this->app->instance(PushNotificationService::class, $push);

        $withDevice = User::factory()->create();
        PushSubscription::factory()->create(['user_id' => $withDevice->id]);
        User::factory()->create();

        $this->artisan('notify:daily-monitoring')->assertExitCode(0);
    }

    public function test_does_not_send_twice_on_same_day(): void
    {
        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')->once();
        $this->app->instance(PushNotificationService::class, $push);

        $user = User::factory()->create();
        PushSubscription::factory()->create(['user_id' => $user->id]);

        $this->artisan('notify:daily-monitoring')->assertExitCode(0);
        $this->artisan('notify:daily-monitoring')->assertExitCode(0);
    }
}
```

- [ ] **Step 6: Jalankan test — verifikasi PASS**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Commands/NotifyDailyMonitoringTest.php`
Expected: PASS (2 tests)

- [ ] **Step 7: Verifikasi command terdaftar**

Run: `./vendor/bin/sail artisan list | grep notify:daily-monitoring`
Expected: command tercetak.

- [ ] **Step 8: Pint + commit**

```bash
./vendor/bin/sail bin pint --dirty --format agent
git add app/Console/Commands/NotifyDailyMonitoring.php bootstrap/app.php config/app.php .env.example tests/Feature/Commands/NotifyDailyMonitoringTest.php
git commit -m "feat: add daily monitoring reminder command"
```

---

### Task 5: Notifikasi aktivitas anggota farm (job + observers)

**Files:**
- Create: `app/Jobs/NotifyFarmActivity.php`
- Create: `app/Observers/DailyMonitoringObserver.php`
- Create: `app/Observers/NutrientAdditionObserver.php`
- Create: `app/Observers/PhDownLogObserver.php`
- Modify: `app/Providers/AppServiceProvider.php` (register observers)
- Test: `tests/Feature/FarmActivity/NotifyFarmActivityTest.php`

**Interfaces:**
- Consumes: `PushNotificationService::sendToUser()` (Task 3); model `DailyMonitoring|NutrientAddition|PhDownLog` (masing-masing punya `user_id`, `user()`, `tank()`).
- Produces: job `NotifyFarmActivity` (ShouldQueue) dengan constructor `(DailyMonitoring|NutrientAddition|PhDownLog $entity)`; dispatc dari observers pada event `created`.

- [ ] **Step 1: Tulis job**

```php
<?php

namespace App\Jobs;

use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\PhDownLog;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyFarmActivity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public DailyMonitoring|NutrientAddition|PhDownLog $entity,
    ) {
    }

    public function handle(PushNotificationService $push): void
    {
        $entity = $this->entity->load(['tank.farm', 'user']);
        $tank = $entity->tank;
        $farm = $tank?->farm;
        $actor = $entity->user;

        if (! $farm || ! $actor) {
            return;
        }

        $title = 'Aktivitas Farm';
        $body = match (true) {
            $entity instanceof DailyMonitoring => "{$actor->name} mencatat PPM {$entity->ppm} & pH {$entity->ph} — {$tank->name}",
            $entity instanceof NutrientAddition => "{$actor->name} menambah AB Mix — {$tank->name} (PPM {$entity->ppm_before} → {$entity->ppm_after})",
            default => "{$actor->name} menurunkan pH — {$tank->name} (pH {$entity->ph_before} → {$entity->ph_after})",
        };

        $farm->users()
            ->where('users.id', '!=', $entity->user_id)
            ->get()
            ->each(fn (User $user) => $push->sendToUser($user, $title, $body, route('daily-monitoring.index')));
    }
}
```

- [ ] **Step 2: Tulis tiga observer**

`app/Observers/DailyMonitoringObserver.php`:

```php
<?php

namespace App\Observers;

use App\Jobs\NotifyFarmActivity;
use App\Models\Farm\DailyMonitoring;

class DailyMonitoringObserver
{
    public function created(DailyMonitoring $monitoring): void
    {
        NotifyFarmActivity::dispatch($monitoring);
    }
}
```

`app/Observers/NutrientAdditionObserver.php`:

```php
<?php

namespace App\Observers;

use App\Jobs\NotifyFarmActivity;
use App\Models\Farm\NutrientAddition;

class NutrientAdditionObserver
{
    public function created(NutrientAddition $addition): void
    {
        NotifyFarmActivity::dispatch($addition);
    }
}
```

`app/Observers/PhDownLogObserver.php`:

```php
<?php

namespace App\Observers;

use App\Jobs\NotifyFarmActivity;
use App\Models\Farm\PhDownLog;

class PhDownLogObserver
{
    public function created(PhDownLog $log): void
    {
        NotifyFarmActivity::dispatch($log);
    }
}
```

- [ ] **Step 3: Register observer di AppServiceProvider::boot()**

Tambahkan use statement dan baris berikut di `boot()` (setelah baris `PhDownLog::observe(ActivityLogObserver::class);`):

```php
use App\Observers\DailyMonitoringObserver;
use App\Observers\NutrientAdditionObserver;
use App\Observers\PhDownLogObserver;
```

```php
        DailyMonitoring::observe(DailyMonitoringObserver::class);
        NutrientAddition::observe(NutrientAdditionObserver::class);
        PhDownLog::observe(PhDownLogObserver::class);
```

- [ ] **Step 4: Tulis feature test**

`tests/Feature/FarmActivity/NotifyFarmActivityTest.php`:

```php
<?php

namespace Tests\Feature\FarmActivity;

use App\Models\Farm;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Tank;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class NotifyFarmActivityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setupFarm(array $roles): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);

        foreach ($roles as $name => $role) {
            $user = User::factory()->create();
            $farm->users()->attach($user->id, ['role' => $role]);
            $users[$name] = $user;
        }

        $users['owner'] = $owner;
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $owner->id]);
        session()->put('selected_farm_id', $farm->id);

        return compact('farm', 'tank', 'users');
    }

    public function test_activity_notifies_other_farm_members_except_actor(): void
    {
        $received = [];
        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')->andReturnUsing(function (User $user) use (&$received): void {
            $received[] = $user->id;
        });
        $this->app->instance(PushNotificationService::class, $push);

        ['tank' => $tank, 'users' => $users] = $this->setupFarm([
            'actor' => 'operator',
            'member' => 'operator',
        ]);

        DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'user_id' => $users['actor']->id,
        ]);

        $this->assertEqualsCanonicalizing([$users['owner']->id, $users['member']->id], $received);
    }

    public function test_job_dispatched_when_record_created(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        ['tank' => $tank, 'users' => $users] = $this->setupFarm(['actor' => 'operator']);

        DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'user_id' => $users['actor']->id,
        ]);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\NotifyFarmActivity::class);
    }
}
```

- [ ] **Step 5: Jalankan test — verifikasi PASS**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/FarmActivity/NotifyFarmActivityTest.php`
Expected: PASS (2 tests)

- [ ] **Step 6: Jalankan test monitoring existing (regresi observer)**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/DailyMonitoring`
Expected: PASS (observer baru tidak merusak test existing)

- [ ] **Step 7: Pint + commit**

```bash
./vendor/bin/sail bin pint --dirty --format agent
git add app/Jobs/NotifyFarmActivity.php app/Observers/DailyMonitoringObserver.php app/Observers/NutrientAdditionObserver.php app/Observers/PhDownLogObserver.php app/Providers/AppServiceProvider.php tests/Feature/FarmActivity/NotifyFarmActivityTest.php
git commit -m "feat: notify farm members on new activity records"
```

---

### Task 6: PWA layer — vite-plugin-pwa, manifest, icons, meta layout

**Files:**
- Modify: `package.json` (devDep `vite-plugin-pwa`)
- Modify: `vite.config.js`
- Create: `resources/js/pwa-sw.js` (SW tunggal: precache + FCM background)
- Create: `scripts/generate-pwa-icons.py` + hasil icon PNG di `public/icons/`
- Modify: `resources/views/layouts/app.blade.php` (meta manifest, theme-color, apple-touch-icon, csrf)
- Modify: `resources/js/app.js` (registerSW + import firebase) — firebase.js dibuat di Task 7; pada task ini cukup import `virtual:pwa-register`
- Test: `tests/Feature/Frontend/PwaLayoutTest.php`

**Interfaces:**
- Consumes: — (mandiri). Membutuhkan `npm install` berhasil.
- Produces:
  - Manifest `manifest.webmanifest` di build (name "Hydroponic Farm Management", short_name "Hydro Farm", start_url "/", display standalone).
  - Service worker `build/sw.js` (injectManifest) dengan precache asset + FCM `onBackgroundMessage` + `notificationclick`.
  - Icons: `public/icons/icon-192x192.png`, `icon-512x512.png`, `icon-maskable-512x512.png`.

- [ ] **Step 1: Install vite-plugin-pwa**

Run: `./vendor/bin/sail npm install -D vite-plugin-pwa@^1.3`
Expected: terinstal tanpa error peer (Vite 8 didukung).

- [ ] **Step 2: Tulis service worker sumber**

`resources/js/pwa-sw.js`:

```js
import { precacheAndRoute } from 'workbox-precaching';
import { initializeApp } from 'firebase/app';
import { getMessaging, onBackgroundMessage } from 'firebase/messaging/sw';

precacheAndRoute(self.__WB_MANIFEST);

initializeApp({
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
    appId: import.meta.env.VITE_FIREBASE_APP_ID,
});

const messaging = getMessaging();

onBackgroundMessage(messaging, (payload) => {
    const { title, body } = payload.notification ?? {};

    self.registration.showNotification(title ?? 'Hydro Farm', {
        body: body ?? '',
        icon: '/icons/icon-192x192.png',
        badge: '/icons/icon-192x192.png',
        data: { url: payload.data?.url },
    });
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url ?? '/';
    event.waitUntil(clients.openWindow(url));
});
```

- [ ] **Step 3: Update vite.config.js**

Tambahkan import dan plugin (lihat file saat ini):

```js
import { VitePWA } from 'vite-plugin-pwa';
```

Tambahkan `VitePWA` sebagai plugin setelah `tailwindcss()`:

```js
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'pwa-sw.js',
            registerType: 'autoUpdate',
            injectRegister: false,
            manifest: {
                name: 'Hydroponic Farm Management',
                short_name: 'Hydro Farm',
                description: 'Sistem manajemen farm hidroponik',
                lang: 'id',
                theme_color: '#f8f6f2',
                background_color: '#f8f6f2',
                display: 'standalone',
                start_url: '/',
                scope: '/',
                icons: [
                    { src: '/icons/icon-192x192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/icons/icon-512x512.png', sizes: '512x512', type: 'image/png' },
                    { src: '/icons/icon-maskable-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
            },
            workbox: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2,woff}'],
            },
            devOptions: {
                enabled: false,
            },
        }),
```

Tambahkan aturan `Service-Worker-Allowed` untuk scope root (SW keluar di `/build/sw.js`):

`public/.htaccess` — tambahkan di dalam `<IfModule mod_headers.c>` (atau buat blok baru):

```apache
    <FilesMatch "sw\.js$">
        Header set Service-Worker-Allowed "/"
    </FilesMatch>
```

- [ ] **Step 4: Generate icons**

`scripts/generate-pwa-icons.py`:

```python
#!/usr/bin/env python3
from PIL import Image, ImageDraw
import os

OUT_DIR = "public/icons"
BG = (255, 206, 84, 255)   # #ffce54
FG = (26, 28, 30, 255)     # #1a1c1e


def droplet(size, scale, center):
    cx, cy = center
    r = size * scale
    return [
        (cx, cy - r * 1.35),
        (cx - r * 1.05, cy - r * 0.15),
        (cx - r, cy + r * 0.45),
        (cx - r * 0.55, cy + r * 0.95),
        (cx, cy + r * 1.2),
        (cx + r * 0.55, cy + r * 0.95),
        (cx + r, cy + r * 0.45),
        (cx + r * 1.05, cy - r * 0.15),
    ]


def make_icon(size, path, maskable=False):
    img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    if maskable:
        d.rectangle([0, 0, size - 1, size - 1], fill=BG)
        d.polygon(droplet(size, 0.21, (size / 2, size / 2)), fill=FG)
    else:
        d.rounded_rectangle([0, 0, size - 1, size - 1], radius=int(size * 0.22), fill=BG)
        d.polygon(droplet(size, 0.28, (size / 2, size / 2 + size * 0.03)), fill=FG)
    img.save(path)


if __name__ == "__main__":
    os.makedirs(OUT_DIR, exist_ok=True)
    make_icon(192, f"{OUT_DIR}/icon-192x192.png")
    make_icon(512, f"{OUT_DIR}/icon-512x512.png")
    make_icon(512, f"{OUT_DIR}/icon-maskable-512x512.png", maskable=True)
    print("Icons generated in", OUT_DIR)
```

Jalankan (host python, bukan sail — PIL tidak ada di container):
Run: `python3 scripts/generate-pwa-icons.py`
Expected: 3 file PNG muncul di `public/icons/`. Verifikasi: `ls -la public/icons/`.

> Catatan: user boleh mengganti icon ini nanti; file PNG di-commit ke repo.

- [ ] **Step 5: Update layouts/app.blade.php**

Tambahkan di `<head>` (setelah `<meta name="viewport" ...>`):

```blade
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f8f6f2">
    <link rel="manifest" href="{{ asset('build/manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/icon-192x192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192x192.png') }}">
```

Ubah meta viewport menjadi: `content="width=device-width, initial-scale=1.0, viewport-fit=cover"`.

- [ ] **Step 6: Register SW di app.js**

Di awal `resources/js/app.js` tambahkan:

```js
import { registerSW } from 'virtual:pwa-register';

registerSW({ immediate: true });
```

- [ ] **Step 7: Build**

Run: `./vendor/bin/sail npm run build`
Expected: build sukses; output berisi `manifest.webmanifest` dan `sw.js` di `public/build/`. Verifikasi: `ls public/build/ | grep -E 'sw|manifest'`.

- [ ] **Step 8: Tulis blade test**

`tests/Feature/Frontend/PwaLayoutTest.php`:

```php
<?php

namespace Tests\Feature\Frontend;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_renders_pwa_meta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertSee('theme-color', false);
    }
}
```

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Frontend/PwaLayoutTest.php`
Expected: PASS

- [ ] **Step 9: Pint + commit**

```bash
git add package.json package-lock.json vite.config.js resources/js/pwa-sw.js resources/js/app.js resources/views/layouts/app.blade.php public/.htaccess public/icons scripts/generate-pwa-icons.py tests/Feature/Frontend/PwaLayoutTest.php
git commit -m "feat: add PWA manifest, icons, and service worker"
```

> Perhatian: `public/build` di-gitignore (baris `/public/build` di `.gitignore`). `manifest.webmanifest` & `sw.js` dihasilkan saat `npm run build` (pada deployment). Yang wajib di-commit: `public/.htaccess` (header `Service-Worker-Allowed`) dan `public/icons/*.png`.

---

### Task 7: Frontend FCM — firebase.js + integrasi logout

**Files:**
- Modify: `package.json` (dependency `firebase`)
- Create: `resources/js/firebase.js`
- Modify: `resources/js/app.js` (import './firebase')
- Modify: `resources/views/partials/sidebar.blade.php` (logout form diberi kelas `js-logout-form`) — untuk intercept cleanup token
- Modify: `.env.example` (tambah `VITE_FIREBASE_*`)
- Test: `tests/Feature/Frontend/PwaLayoutTest.php` (tambah assertion `assertSee('firebase.js', false)` tidak wajib — verifikasi manual + test layout tetap)

**Interfaces:**
- Consumes: endpoint `POST/DELETE /push-subscriptions` (Task 2); env `VITE_FIREBASE_*`.
- Produces: modul JS `resources/js/firebase.js` (di-import app.js) yang meminta permission, mendaftarkan token, handle `onMessage` (notif saat app terbuka), dan membersihkan token saat logout. Karena ENV user belum ada, jalankan path hanya berjalan saat semua env terisi (guard).

- [ ] **Step 1: Install firebase**

Run: `./vendor/bin/sail npm install firebase@^12`

- [ ] **Step 2: Tulis resources/js/firebase.js**

```js
const firebaseConfig = () => {
    const config = {
        apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
        projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
        messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
        appId: import.meta.env.VITE_FIREBASE_APP_ID,
    };

    return config.apiKey && config.projectId && config.messagingSenderId && config.appId ? config : null;
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const registerDeviceToken = async (messaging) => {
    try {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            return;
        }

        const token = await getToken(messaging, {
            vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY,
        });

        if (!token) {
            return;
        }

        localStorage.setItem('fcm_token', token);

        await fetch('/push-subscriptions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ fcm_token: token, platform: 'android' }),
        });
    } catch (error) {
        console.error('FCM registration failed:', error);
    }
};

const cleanupTokenOnLogout = () => {
    document.addEventListener('submit', async (event) => {
        if (!(event.target instanceof HTMLFormElement)) {
            return;
        }

        if (!event.target.classList.contains('js-logout-form')) {
            return;
        }

        event.preventDefault();

        const token = localStorage.getItem('fcm_token');
        try {
            if (token) {
                await fetch('/push-subscriptions', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ fcm_token: token }),
                });
            }
        } catch (error) {
            console.error('FCM token cleanup failed:', error);
        } finally {
            localStorage.removeItem('fcm_token');
            event.target.submit();
        }
    });
};

const initFirebaseMessaging = async () => {
    const config = firebaseConfig();
    if (!config || !('serviceWorker' in navigator) || !('PushManager' in window)) {
        return;
    }

    try {
        const { initializeApp } = await import('firebase/app');
        const { getMessaging, getToken, onMessage, isSupported } = await import('firebase/messaging');

        if (!(await isSupported())) {
            return;
        }

        const app = initializeApp(config);
        const messaging = getMessaging(app);

        onMessage(messaging, (payload) => {
            const { title, body } = payload.notification ?? {};
            if (title) {
                new Notification(title, {
                    body: body ?? '',
                    icon: '/icons/icon-192x192.png',
                });
            }
        });

        await registerDeviceToken(messaging);
    } catch (error) {
        console.error('Firebase init failed:', error);
    }
};

window.addEventListener('DOMContentLoaded', () => {
    initFirebaseMessaging();
    cleanupTokenOnLogout();
});
```

> Catatan: `import()` dinamis menjaga bundle tetap kecil untuk browser tanpa dukungan push; `firebase/messaging` hanya dimuat saat dibutuhkan.

- [ ] **Step 3: Import di app.js**

Di `resources/js/app.js` tambahkan:

```js
import './firebase';
```

- [ ] **Step 4: Tandai form logout**

Di `resources/views/partials/sidebar.blade.php`, ubah `<form method="POST" action="{{ route('logout') }}" class="w-full">` menjadi `class="w-full js-logout-form"`. (Halaman Profil di Task 8 akan memakai kelas yang sama.)

- [ ] **Step 5: Tambah .env.example**

```env
VITE_FIREBASE_API_KEY=
VITE_FIREBASE_PROJECT_ID=
VITE_FIREBASE_MESSAGING_SENDER_ID=
VITE_FIREBASE_APP_ID=
VITE_FIREBASE_VAPID_KEY=
```

- [ ] **Step 6: Build**

Run: `./vendor/bin/sail npm run build`
Expected: build sukses.

- [ ] **Step 7: Jalankan test Frontend (regresi)**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Frontend`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add package.json package-lock.json resources/js/firebase.js resources/js/app.js resources/views/partials/sidebar.blade.php .env.example
git commit -m "feat: add firebase messaging frontend integration"
```

> `public/build` tidak di-commit (gitignore); regenerated saat `npm run build`.

---

### Task 8: Bottom nav mobile + hapus hamburger + halaman Profil

**Files:**
- Create: `resources/views/partials/bottom-nav.blade.php`
- Modify: `resources/views/layouts/app.blade.php` (include bottom-nav + body `pb`)
- Modify: `resources/views/partials/topbar.blade.php` (hilangkan hamburger di mobile)
- Modify: `resources/views/partials/sidebar.blade.php` (sidebar desktop-only)
- Modify: `resources/js/app.js` (hapus logika mobile sidebar; tambah toggle Catat)
- Create: `app/Http/Controllers/ProfileController.php`
- Create: `resources/views/profile/index.blade.php`
- Create: `routes/profile.php`
- Modify: `routes/web.php` (require profile.php)
- Test: `tests/Feature/Profile/ProfilePageTest.php`

**Interfaces:**
- Consumes: route existing `dashboard`, `daily-monitoring.create`, `nutrient-addition.create`, `ph-down-log.create`, `daily-monitoring.index`, `farm.index`, `tank.index`, `reports.monitoring`, `reports.nutrient`, `reports.ph-down`, `activity-logs.index`, `logout`.
- Produces: route `profile` (GET, auth) → view `profile/index.blade.php`.

- [ ] **Step 1: Tulis bottom-nav partial**

`resources/views/partials/bottom-nav.blade.php`:

```blade
@auth
    <nav id="bottomNav"
        class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-4 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-2px_12px_rgba(0,0,0,0.05)] backdrop-blur-xl lg:hidden">
        <a href="{{ route('dashboard') }}"
            class="flex flex-col items-center gap-0.5 py-2.5 text-[10px] font-semibold {{ request()->routeIs('dashboard') ? 'text-[#d4a020]' : 'text-slate-500' }}">
            <i class="bi {{ request()->routeIs('dashboard') ? 'bi-grid-1x2-fill' : 'bi-grid-1x2' }} text-xl"></i>
            Dashboard
        </a>

        <div class="relative flex flex-col items-center">
            <button id="catatBtn" type="button"
                class="flex w-full flex-col items-center gap-0.5 py-2.5 text-[10px] font-semibold {{ request()->routeIs('daily-monitoring.create') || request()->routeIs('nutrient-addition.create') || request()->routeIs('ph-down-log.create') ? 'text-[#d4a020]' : 'text-slate-500' }}">
                <i class="bi bi-plus-circle-fill text-xl"></i>
                Catat
            </button>
            <div id="catatMenu" class="absolute bottom-full right-0 mb-2 hidden w-52 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl">
                <a href="{{ route('daily-monitoring.create') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                    <i class="bi bi-thermometer-half text-base text-slate-400"></i>
                    Monitoring (PPM & pH)
                </a>
                <a href="{{ route('nutrient-addition.create') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                    <i class="bi bi-droplet text-base text-slate-400"></i>
                    AB Mix
                </a>
                <a href="{{ route('ph-down-log.create') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                    <i class="bi bi-flask text-base text-slate-400"></i>
                    pH Down
                </a>
            </div>
        </div>

        <a href="{{ route('daily-monitoring.index') }}"
            class="flex flex-col items-center gap-0.5 py-2.5 text-[10px] font-semibold {{ request()->routeIs('daily-monitoring.*') ? 'text-[#d4a020]' : 'text-slate-500' }}">
            <i class="bi {{ request()->routeIs('daily-monitoring.*') ? 'bi-clock-history-fill' : 'bi-clock-history' }} text-xl"></i>
            Riwayat
        </a>

        <a href="{{ route('profile') }}"
            class="flex flex-col items-center gap-0.5 py-2.5 text-[10px] font-semibold {{ request()->routeIs('profile') ? 'text-[#d4a020]' : 'text-slate-500' }}">
            <i class="bi {{ request()->routeIs('profile') ? 'bi-person-fill' : 'bi-person' }} text-xl"></i>
            Profil
        </a>
    </nav>
@endauth
```

- [ ] **Step 2: Include bottom-nav + body padding di layout**

Di `resources/views/layouts/app.blade.php`:
- Ubah `<body class="min-h-screen bg-[#f8f6f2] text-slate-900 antialiased">` menjadi tambah `pb-16 lg:pb-0`.
- Setelah `@include('partials.chat-widget')`, tambah `@include('partials.bottom-nav')`.

- [ ] **Step 3: Topbar — hilangkan hamburger di mobile**

Di `resources/views/partials/topbar.blade.php`, ganti blok mobile pertama menjadi brand terpusat:

```blade
    <div class="flex items-center justify-center gap-2 lg:hidden">
        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-[#ffce54] text-[#1a1c1e]">
            <i class="bi bi-droplet-half"></i>
        </span>
        <span class="text-base font-semibold text-slate-900">Hydro Farm</span>
    </div>
```

- [ ] **Step 4: Sidebar — desktop-only**

Di `resources/views/partials/sidebar.blade.php`:
- Hapus seluruh `<div id="mobileSidebarOverlay" ...></div>`.
- Ubah `<aside id="sidebar" ...>` class menjadi:

```blade
    class="hidden lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-[280px] lg:shrink-0 lg:flex-col overflow-y-auto border-r border-slate-200 bg-white px-4 py-6"
```

- Hapus blok `{{-- Mobile header --}}` (div `flex items-center justify-between lg:hidden`) beserta `#closeSidebarBtn`.
- Form logout di blok bawah: ubah class menjadi `class="w-full js-logout-form"` (sudah dari Task 7 — pastikan konsisten).

- [ ] **Step 5: app.js — bersihkan logika mobile sidebar + toggle Catat**

Di `resources/js/app.js`, **biarkan baris import di paling atas tetap** (hasil Task 6 & 7: `import { registerSW } from 'virtual:pwa-register'; registerSW({ immediate: true });` dan `import './firebase';`). Ganti isi blok `DOMContentLoaded` menjadi (pertahankan logika collapse desktop, hapus mobile open/close/overlay, tambah toggle menu Catat):

```js
window.addEventListener('DOMContentLoaded', () => {
    const sidebar                 = document.getElementById('sidebar');
    const desktopSidebarToggleBtn = document.getElementById('desktopSidebarToggleBtn');

    const STORAGE_KEY = 'sidebar_desktop_collapsed';

    const isDesktop = () => window.innerWidth >= 1024;

    const setDesktopCollapsed = (collapsed) => {
        if (!sidebar) return;
        if (collapsed) {
            sidebar.classList.add('sidebar-collapsed');
        } else {
            sidebar.classList.remove('sidebar-collapsed');
        }
        localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
    };

    if (isDesktop() && sidebar) {
        const stored = localStorage.getItem(STORAGE_KEY);
        const shouldCollapse = stored === '1';
        sidebar.style.transition = 'none';
        setDesktopCollapsed(shouldCollapse);
        sidebar.offsetHeight;
        requestAnimationFrame(() => { sidebar.style.transition = ''; });
    }

    desktopSidebarToggleBtn?.addEventListener('click', () => {
        const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
        setDesktopCollapsed(!isCollapsed);
    });

    window.addEventListener('resize', () => {
        if (!isDesktop() && sidebar) {
            sidebar.classList.remove('sidebar-collapsed');
        } else if (isDesktop() && sidebar) {
            const stored = localStorage.getItem(STORAGE_KEY);
            setDesktopCollapsed(stored === '1');
        }
    });

    const catatBtn = document.getElementById('catatBtn');
    const catatMenu = document.getElementById('catatMenu');
    catatBtn?.addEventListener('click', () => {
        catatMenu?.classList.toggle('hidden');
    });
    document.addEventListener('click', (event) => {
        if (!catatMenu || catatMenu.classList.contains('hidden')) return;
        if (!catatBtn?.contains(event.target) && !catatMenu.contains(event.target)) {
            catatMenu.classList.add('hidden');
        }
    });
});
```

> Catatan: `.sidebar-collapsed` didefinisikan di CSS app.css — pertahankan. Verifikasi `.sidebar-collapsed` tidak dipakai elemen mobile (sudah dihapus).

- [ ] **Step 6: ProfileController + route**

`app/Http/Controllers/ProfileController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $farms = $user->farms()->withCount('tanks')->get();

        return view('profile.index', compact('user', 'farms'));
    }
}
```

`routes/profile.php`:

```php
<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/profile', [ProfileController::class, 'index'])
    ->middleware('auth')
    ->name('profile');
```

Di `routes/web.php`, tambah `require __DIR__.'/profile.php';`.

- [ ] **Step 7: Halaman Profil**

`resources/views/profile/index.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Profil')

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-2xl space-y-6">
                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6">
                        <div class="flex items-center gap-4">
                            <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[#ffce54] text-xl text-[#1a1c1e]">
                                <i class="bi bi-person-fill"></i>
                            </span>
                            <div>
                                <h1 class="text-xl font-semibold text-slate-900">{{ $user->name }}</h1>
                            </div>
                        </div>

                        @if ($farms->isNotEmpty())
                            <div class="mt-5 space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Farm Saya</p>
                                @foreach ($farms as $farm)
                                    <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3">
                                        <span class="text-sm font-semibold text-slate-700">{{ $farm->name }}</span>
                                        <span class="rounded-full bg-[#ffce54]/20 px-2.5 py-0.5 text-xs font-semibold text-[#d4a020]">
                                            {{ ucfirst($farm->pivot->role) }} · {{ $farm->tanks_count }} tank
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="rounded-[2rem] border border-slate-200 bg-white p-4">
                        <p class="px-2 pb-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Menu Lainnya</p>
                        <div class="grid gap-1">
                            <a href="{{ route('farm.index') }}" class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                                <i class="bi bi-buildings-fill text-base text-slate-400"></i> Farm
                            </a>
                            <a href="{{ route('tank.index') }}" class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                                <i class="bi bi-water text-base text-slate-400"></i> Tank
                            </a>
                            <a href="{{ route('reports.monitoring') }}" class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                                <i class="bi bi-bar-chart-line text-base text-slate-400"></i> Laporan Monitoring
                            </a>
                            <a href="{{ route('reports.nutrient') }}" class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                                <i class="bi bi-pie-chart text-base text-slate-400"></i> Laporan AB Mix
                            </a>
                            <a href="{{ route('reports.ph-down') }}" class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                                <i class="bi bi-graph-down text-base text-slate-400"></i> Laporan pH Down
                            </a>
                            <a href="{{ route('activity-logs.index') }}" class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                                <i class="bi bi-clock-history text-base text-slate-400"></i> Activity Logs
                            </a>
                        </div>
                    </div>

                    <div class="rounded-[2rem] border border-slate-200 bg-white p-4">
                        <form method="POST" action="{{ route('logout') }}" class="js-logout-form w-full">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold text-red-600 hover:bg-red-50">
                                <i class="bi bi-box-arrow-right text-base"></i> Logout
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    </div>
@endsection
```

> Catatan: tabel `users` TIDAK punya kolom `email` (login pakai `name`). Profil menampilkan nama + daftar farm & role user (`$farm->pivot->role`, `$farm->tanks_count` dari `withCount('tanks')`).

- [ ] **Step 8: Tulis feature test profil**

`tests/Feature/Profile/ProfilePageTest.php`:

```php
<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_auth(): void
    {
        $this->get(route('profile'))->assertRedirect('/login');
    }

    public function test_profile_page_shows_user_info_and_secondary_links(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile'))
            ->assertOk()
            ->assertSee('Profil')
            ->assertSee($user->name)
            ->assertSee(route('tank.index'));
    }
}
```

- [ ] **Step 9: Jalankan test — verifikasi PASS**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Profile/ProfilePageTest.php`
Expected: PASS (2 tests). Test memakai `assertSee($user->name)` — aman tanpa kolom email.

- [ ] **Step 10: Jalankan seluruh suite Frontend + regresi**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Frontend`
Expected: PASS

Run: `./vendor/bin/sail artisan test --compact`
Expected: PASS seluruh suite.

- [ ] **Step 11: Pint + build + commit**

```bash
./vendor/bin/sail bin pint --dirty --format agent
./vendor/bin/sail npm run build
git add resources/views/partials/bottom-nav.blade.php resources/views/layouts/app.blade.php resources/views/partials/topbar.blade.php resources/views/partials/sidebar.blade.php resources/js/app.js app/Http/Controllers/ProfileController.php resources/views/profile/index.blade.php routes/profile.php routes/web.php tests/Feature/Profile/ProfilePageTest.php
git commit -m "feat: add mobile bottom nav, desktop-only sidebar, and profile page"
```

---

## Verifikasi Manual (post-eksekusi, di luar deployment)

- [ ] `./vendor/bin/sail artisan route:list` — tampil `profile`, `push-subscriptions.store`, `push-subscriptions.destroy`.
- [ ] Buka app di browser → install via Chrome Android "Add to Home Screen" (butuh HTTPS/domain — disiapkan saat deployment).
- [ ] Lighthouse PWA audit di Chrome DevTools (tab Application → Manifest) — manifest & SW terdaftar, `sw.js` punya `Service-Worker-Allowed: /`.
- [ ] App shell (CSS/JS) ter-cache; halaman dimuat cepat setelah kunjungan kedua.
- [ ] Bottom nav hanya tampil di mobile; sidebar hanya di desktop; hamburger tidak muncul di mobile.
- [ ] Menu "Catat" membuka 3 link quick-add; menutup saat klik di luar.
- [ ] Halaman Profil menampilkan info user + link sekunder + logout.
- [ ] FCM end-to-end (setelah ENV user diisi + `npm run build` + `artisan migrate`): izin notif diminta, token tersimpan di `push_subscriptions`, `artisan notify:daily-monitoring` mengirim pengingat, dan aktivitas anggota farm memicu notifikasi.

## Catatan Penundaan (Deferred / Out of Scope)

- Nilai ENV FCM disusulkan user — hingga terisi, notifikasi tidak bisa diuji end-to-end; seluruh test tetap hijau via mock.
- Deployment (Cloudflare Tunnel, scheduler `schedule:work`/cron, queue worker produksi) TIDAK dilakukan dalam plan ini.
- iOS PWA push (jalur VAPID) dan offline data-entry di-defer.
