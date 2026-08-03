# Fitur Reminder Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan fitur reminder yang bisa menarget pembuat sendiri, semua member kebun, atau user/staff tertentu — dengan dukungan sekali/berulang, advance reminder, tracking done per occurrence, kalender, dan push notification FCM.

**Architecture:** Laravel monolith Blade. Reminder disimpan sebagai `reminders` + `reminder_targets` (polymorphic ke User/Staff) + `reminder_occurrences` (instance konkret). Scheduler `reminders:dispatch` tiap menit mengirim FCM via `PushNotificationService` yang sudah ada. Push subscription diubah jadi polymorphic `subscribable` agar User dan Staff sama-sama bisa menerima push. Hierarki target: owner (2) > manager (1) > staff (0).

**Tech Stack:** PHP 8.3+, Laravel 13, Blade + Tailwind v4, PostgreSQL (Sail), FCM via kreait/firebase-php, PHPUnit 12.

## Global Constraints

- Semua perintah dijalankan lewat Sail: `vendor/bin/sail artisan ...`, `vendor/bin/sail npm run ...`, `vendor/bin/sail bin pint --dirty --format agent`.
- Semua teks UI berbahasa Indonesia.
- Ikuti pola kode existing: constructor property promotion, return type declarations, casts() method, `#[Fillable]`/`#[Hidden]` attribute atau `$fillable` sesuai file sekitar.
- Setiap perubahan wajib ditest; setelah mengubah file PHP jalankan `vendor/bin/sail bin pint --dirty --format agent`.
- Jangan hapus test yang ada.
- Route pattern: guard `auth` untuk `/farm/{farm}/reminders/*`, guard `staff` untuk `/staff/reminders/*`.
- Nama route prefix `farm.reminders.*` dan `staff.reminders.*`.
- Soft delete untuk reminder (`deleted_at`); target & occurrence ikut cascade saat reminder dihapus permanent (FK cascadeOnDelete), bukan soft delete.
- Jangan membuat fitur di luar spec (Google Calendar sync, in-app notification, email).

---

### Task 1: Migration reminder_occurrences, reminder_targets, reminders + enum

**Files:**
- Create: `app/Enums/ReminderStatus.php`
- Create: `app/Enums/RecurrenceType.php`
- Create: `database/migrations/2026_08_03_100001_create_reminders_table.php`
- Create: `database/migrations/2026_08_03_100002_create_reminder_targets_table.php`
- Create: `database/migrations/2026_08_03_100003_create_reminder_occurrences_table.php`
- Create: `tests/Unit/Enums/ReminderStatusEnumTest.php`

**Interfaces:**
- Produces: enum `App\Enums\ReminderStatus` dengan cases `Pending`, `Done`, `Skipped` dan method `values(): array`; enum `App\Enums\RecurrenceType` dengan cases `None`, `Interval`, `Weekly`, `Monthly` dan method `values(): array`.
- Produces: tabel `reminders`, `reminder_targets`, `reminder_occurrences` sesuai skema di spec.

- [ ] **Step 1: Tulis test enum yang gagal**

```php
<?php

namespace Tests\Unit\Enums;

use App\Enums\ReminderStatus;
use App\Enums\RecurrenceType;
use PHPUnit\Framework\TestCase;

class ReminderStatusEnumTest extends TestCase
{
    public function test_reminder_status_has_expected_cases(): void
    {
        $this->assertSame(['pending', 'done', 'skipped'], ReminderStatus::values());
        $this->assertSame('pending', ReminderStatus::Pending->value);
        $this->assertSame('done', ReminderStatus::Done->value);
        $this->assertSame('skipped', ReminderStatus::Skipped->value);
    }

    public function test_recurrence_type_has_expected_cases(): void
    {
        $this->assertSame(['none', 'interval', 'weekly', 'monthly'], RecurrenceType::values());
    }
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi gagal**

Run: `vendor/bin/sail artisan test --compact tests/Unit/Enums/ReminderStatusEnumTest.php`
Expected: FAIL — class `App\Enums\ReminderStatus` not found.

- [ ] **Step 3: Implementasi enum**

```php
<?php

namespace App\Enums;

enum ReminderStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Skipped = 'skipped';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

```php
<?php

namespace App\Enums;

enum RecurrenceType: string
{
    case None = 'none';
    case Interval = 'interval';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

- [ ] **Step 4: Buat migrasi dengan artisan**

Run: `vendor/bin/sail artisan make:migration create_reminders_table --no-interaction`
Run: `vendor/bin/sail artisan make:migration create_reminder_targets_table --no-interaction`
Run: `vendor/bin/sail artisan make:migration create_reminder_occurrences_table --no-interaction`
(Jika nama file timestamp bentrok, sesuaikan nama file migrasi yang dihasilkan; urutan pembuatan tabel: reminders → reminder_targets → reminder_occurrences.)

- [ ] **Step 5: Isi isi migrasi**

`create_reminders_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->morphs('created_by');
            $table->string('title');
            $table->text('body');
            $table->dateTime('starts_at');
            $table->json('recurrence')->nullable();
            $table->unsignedInteger('advance_notify_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('farm_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
```

`create_reminder_targets_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();
            $table->morphs('targetable');
            $table->timestamps();

            $table->index(['targetable_type', 'targetable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_targets');
    }
};
```

`create_reminder_occurrences_table.php`:

```php
<?php

use App\Enums\ReminderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->dateTime('advance_notify_at')->nullable();
            $table->dateTime('advance_notified_at')->nullable();
            $table->dateTime('notified_at')->nullable();
            $table->string('status')->default(ReminderStatus::Pending->value);
            $table->morphs('completed_by');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['reminder_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_occurrences');
    }
};
```

Catatan: `morphs('completed_by')` menghasilkan kolom `completed_by_type` dan `completed_by_id` (nullable otomatis untuk morphs pada kolom yang bukan primary key? — Laravel `morphs` menghasilkan nullable. Jika tidak nullable, tambahkan `->nullable()` secara manual). Verifikasi dengan `vendor/bin/sail artisan migrate --pretend` atau jalankan test database di Task 3.

- [ ] **Step 6: Jalankan test enum**

Run: `vendor/bin/sail artisan test --compact tests/Unit/Enums/ReminderStatusEnumTest.php`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Enums database/migrations
git commit -m "feat: tambah enum status & tipe recurrence + migrasi tabel reminder"
```

---

### Task 2: Migrasi push_subscriptions ke polymorphic + update service, factory, controller

**Files:**
- Create: `database/migrations/2026_08_03_100004_modify_push_subscriptions_table.php`
- Modify: `app/Models/PushSubscription.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/Farm/Staff.php`
- Modify: `app/Services/PushNotificationService.php`
- Modify: `database/factories/PushSubscriptionFactory.php`
- Modify: `app/Http/Controllers/PushSubscriptionController.php`
- Modify: `routes/pwa.php`
- Modify: `tests/Feature/PushSubscription/PushSubscriptionControllerTest.php`
- Create: `tests/Feature/PushSubscription/PushSubscriptionMigrationTest.php`
- Create: `tests/Feature/PushSubscription/StaffPushSubscriptionTest.php`

**Interfaces:**
- Produces: kolom `subscribable_type`, `subscribable_id` di `push_subscriptions`, migrasi data user_id → subscribable.
- Produces: `PushNotificationService::sendToUser(User|Staff $user, string $title, string $body, ?string $url = null): void`.
- Produces: route `staff.push-subscriptions.store` dan `staff.push-subscriptions.destroy` (POST/DELETE `/staff/push-subscriptions`).
- Produces: `PushSubscription::morphTo subscribable`, `User::morphMany pushSubscriptions`, `Staff::morphMany pushSubscriptions`.
- Produces: factory `PushSubscriptionFactory` state `forSubscribable`.

- [ ] **Step 1: Tulis test migrasi data + staff push subscription yang gagal**

`tests/Feature/PushSubscription/PushSubscriptionMigrationTest.php`:

```php
<?php

namespace Tests\Feature\PushSubscription;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PushSubscriptionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_user_id_is_migrated_to_subscribable(): void
    {
        $user = User::factory()->create();

        // Simulasikan skema lama sebelum migrasi polymorphic
        Schema::drop('push_subscriptions');
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fcm_token')->unique();
            $table->string('platform')->default('android');
            $table->string('device_info')->nullable();
            $table->timestamps();
        });

        DB::table('push_subscriptions')->insert([
            'user_id' => $user->id,
            'fcm_token' => 'legacy-token-123',
            'platform' => 'android',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_08_03_100004_modify_push_subscriptions_table.php');
        $migration->up();

        $row = DB::table('push_subscriptions')->where('fcm_token', 'legacy-token-123')->first();

        $this->assertSame(User::class, $row->subscribable_type);
        $this->assertSame($user->id, $row->subscribable_id);
        $this->assertDatabaseMissing('push_subscriptions', ['user_id' => $user->id]);
    }
}
```

Catatan: panggil `$migration->up()` langsung (bukan `artisan migrate`) agar tidak ter-skip oleh migrations table. Tambahkan import `Illuminate\Database\Schema\Blueprint` di test.

`tests/Feature/PushSubscription/StaffPushSubscriptionTest.php`:

```php
<?php

namespace Tests\Feature\PushSubscription;

use App\Models\Farm;
use App\Models\Farm\Staff;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffPushSubscriptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_can_store_token(): void
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);

        $this->actingAs($staff, 'staff')->postJson(route('staff.push-subscriptions.store'), [
            'fcm_token' => 'staff-token-abc',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_type' => Staff::class,
            'subscribable_id' => $staff->id,
            'fcm_token' => 'staff-token-abc',
        ]);
    }

    public function test_staff_can_delete_own_token(): void
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);
        $subscription = \App\Models\PushSubscription::factory()->create([
            'subscribable_type' => Staff::class,
            'subscribable_id' => $staff->id,
        ]);

        $this->actingAs($staff, 'staff')->deleteJson(route('staff.push-subscriptions.destroy'), [
            'fcm_token' => $subscription->fcm_token,
        ])->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $subscription->id]);
    }
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/PushSubscription`
Expected: FAIL — kolom `subscribable_type` belum ada (migration file belum dibuat); route `staff.push-subscriptions.store` tidak terdaftar.

- [ ] **Step 3: Buat migration modify push_subscriptions**

Buat file `database/migrations/2026_08_03_100004_modify_push_subscriptions_table.php` (jangan ubah migration awal yang sudah ada):

```php
<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('push_subscriptions', 'user_id')) {
            return;
        }

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->nullableMorphs('subscribable');
        });

        DB::table('push_subscriptions')
            ->whereNotNull('user_id')
            ->update([
                'subscribable_type' => User::class,
                'subscribable_id' => DB::raw('user_id'),
            ]);

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
        });

        DB::table('push_subscriptions')
            ->where('subscribable_type', User::class)
            ->update(['user_id' => DB::raw('subscribable_id')]);

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropMorphs('subscribable');
        });
    }
};
```

Alur skema: fresh install maupun database existing — migration awal membuat `user_id`, lalu migration modify menambah `subscribable_*`, memindah data, dan drop `user_id`. Hasil akhir konsisten: hanya `subscribable_type` + `subscribable_id`.

Update `bootstrap/app.php` `shouldRenderJsonWhen` agar route staff push-subscriptions juga merespons JSON:

```php
$exceptions->shouldRenderJsonWhen(
    fn (Request $request) => $request->is('api/*')
        || $request->is('push-subscriptions')
        || $request->is('staff/push-subscriptions'),
);
```

- [ ] **Step 4: Update model PushSubscription**

```php
<?php

namespace App\Models;

use App\Models\Farm\Staff;
use Database\Factories\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PushSubscription extends Model
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'subscribable_type',
        'subscribable_id',
        'fcm_token',
        'platform',
        'device_info',
    ];

    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

- [ ] **Step 5: Update User & Staff**

`app/Models/User.php` — tambah relasi (import `App\Models\PushSubscription` dan `MorphMany`):

```php
public function pushSubscriptions(): MorphMany
{
    return $this->morphMany(PushSubscription::class, 'subscribable');
}
```

`app/Models/Farm/Staff.php` — tambah relasi sama:

```php
public function pushSubscriptions(): MorphMany
{
    return $this->morphMany(PushSubscription::class, 'subscribable');
}
```

- [ ] **Step 6: Update PushNotificationService**

`app/Services/PushNotificationService.php` — ganti tipe param `sendToUser`:

```php
use App\Models\Farm\Staff;
use App\Models\User;

public function sendToUser(User|Staff $user, string $title, string $body, ?string $url = null): void
{
    $subscriptions = $user->pushSubscriptions()->get();
    // ... (body method tidak berubah)
}
```

- [ ] **Step 7: Update factory**

`database/factories/PushSubscriptionFactory.php` — ganti `user_id` dengan polymorphic default + state helper:

```php
public function definition(): array
{
    return [
        'subscribable_type' => User::class,
        'subscribable_id' => User::factory(),
        'fcm_token' => fake()->unique()->regexify('[A-Za-z0-9:._-]{120,160}'),
        'platform' => 'android',
        'device_info' => fake()->optional()->userAgent(),
    ];
}

public function forSubscribable(User|Staff $subscribable): static
{
    return $this->state(fn () => [
        'subscribable_type' => $subscribable::class,
        'subscribable_id' => $subscribable->id,
    ]);
}
```

Import `App\Models\Farm\Staff` di factory.

- [ ] **Step 8: Update PushSubscriptionController + routes**

`app/Http/Controllers/PushSubscriptionController.php` — gunakan `$request->user()` yang polymorphic, tanpa hard-code `user_id`:

```php
public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'fcm_token' => ['required', 'string', 'max:255'],
        'platform' => ['nullable', 'string', 'max:50'],
        'device_info' => ['nullable', 'string', 'max:255'],
    ]);

    $subscribable = $request->user();

    $subscription = PushSubscription::where('fcm_token', $validated['fcm_token'])->first();

    if ($subscription && $subscription->subscribable_id !== $subscribable->id) {
        return response()->json([
            'success' => false,
            'message' => 'Token sudah terdaftar untuk pengguna lain.',
        ], 409);
    }

    PushSubscription::updateOrCreate(
        ['fcm_token' => $validated['fcm_token']],
        [
            'subscribable_type' => $subscribable::class,
            'subscribable_id' => $subscribable->id,
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

    PushSubscription::where('subscribable_type', $request->user()::class)
        ->where('subscribable_id', $request->user()->id)
        ->where('fcm_token', $validated['fcm_token'])
        ->delete();

    return response()->json(['success' => true]);
}
```

`routes/pwa.php` — tambah route staff (guard `staff`):

```php
use App\Http\Controllers\Staff\StaffPushSubscriptionController;
// atau reuse PushSubscriptionController dengan guard staff:

Route::middleware('auth:staff')->group(function () {
    Route::post('/staff/push-subscriptions', [PushSubscriptionController::class, 'store'])
        ->name('staff.push-subscriptions.store');
    Route::delete('/staff/push-subscriptions', [PushSubscriptionController::class, 'destroy'])
        ->name('staff.push-subscriptions.destroy');
});
```

Jika memakai controller yang sama, `$request->user()` otomatis mengembalikan `Staff` saat guard staff. Pastikan route staff tidak bentrok dengan `push-subscriptions.*` yang sudah ada.

- [ ] **Step 9: Update test yang ada + jalankan semua test push subscription**

Update `tests/Feature/PushSubscription/PushSubscriptionControllerTest.php`: ganti semua `user_id` di assert dengan `subscribable_type`/`subscribable_id`, dan factory call `create(['user_id' => ...])` menjadi `create(['subscribable_type' => User::class, 'subscribable_id' => ...])`.

Run: `vendor/bin/sail artisan test --compact tests/Feature/PushSubscription`
Expected: PASS semua test (lama + baru).

- [ ] **Step 10: Commit**

```bash
git add database/migrations app/Models app/Services database/factories app/Http routes tests
git commit -m "feat: push subscription polymorphic untuk user & staff"
```

---

### Task 3: Model Reminder, ReminderTarget, ReminderOccurrence + factory

**Files:**
- Create: `app/Models/Reminder.php`
- Create: `app/Models/Reminder/ReminderTarget.php`
- Create: `app/Models/Reminder/ReminderOccurrence.php`
- Create: `database/factories/ReminderFactory.php`
- Create: `database/factories/Reminder/ReminderTargetFactory.php`
- Create: `database/factories/Reminder/ReminderOccurrenceFactory.php`
- Create: `tests/Unit/Models/ReminderModelTest.php`

**Interfaces:**
- Produces: `App\Models\Reminder` — fillable `farm_id, created_by_type, created_by_id, title, body, starts_at, recurrence, advance_notify_minutes, is_active`; casts `starts_at` datetime, `advance_notify_at` datetime, `recurrence` array, `is_active` bool; relasi `farm(): BelongsTo`, `creator(): MorphTo`, `targets(): HasMany`, `occurrences(): HasMany`; method `isRecurring(): bool` dan `recurrenceType(): RecurrenceType`.
- Produces: `App\Models\Reminder\ReminderTarget` — fillable `reminder_id, targetable_type, targetable_id`; relasi `reminder(): BelongsTo`, `targetable(): MorphTo`.
- Produces: `App\Models\Reminder\ReminderOccurrence` — fillable `reminder_id, scheduled_at, advance_notify_at, advance_notified_at, notified_at, status, completed_by_type, completed_by_id, completed_at`; casts datetime + status enum `ReminderStatus`; relasi `reminder(): BelongsTo`, `completer(): MorphTo`; method `markDone(string $completerType, int $completerId): void`, `markSkipped(): void`.
- Produces: factory `ReminderFactory`, `ReminderTargetFactory`, `ReminderOccurrenceFactory`.
- Produces: test model relation + enum cast + default status.

- [ ] **Step 1: Tulis test model yang gagal**

`tests/Unit/Models/ReminderModelTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Enums\ReminderStatus;
use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReminderModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reminder_relations_and_casts(): void
    {
        $farm = Farm::factory()->create();
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $user->id,
            'recurrence' => ['type' => 'weekly', 'days_of_week' => ['mon']],
        ]);

        $this->assertTrue($reminder->farm->is($farm));
        $this->assertTrue($reminder->creator->is($user));
        $this->assertSame(['type' => 'weekly', 'days_of_week' => ['mon']], $reminder->recurrence);
        $this->assertTrue($reminder->isRecurring());
        $this->assertSame('weekly', $reminder->recurrenceType()->value);
    }

    public function test_occurrence_default_status_pending(): void
    {
        $reminder = Reminder::factory()->create();
        $occurrence = ReminderOccurrence::factory()->create(['reminder_id' => $reminder->id]);

        $this->assertSame(ReminderStatus::Pending, $occurrence->status);
    }

    public function test_occurrence_mark_done_and_skipped(): void
    {
        $reminder = Reminder::factory()->create();
        $user = User::factory()->create();
        $occurrence = ReminderOccurrence::factory()->create(['reminder_id' => $reminder->id]);

        $occurrence->markDone(User::class, $user->id);

        $this->assertSame(ReminderStatus::Done, $occurrence->status);
        $this->assertNotNull($occurrence->completed_at);

        $occurrence->markSkipped();

        $this->assertSame(ReminderStatus::Skipped, $occurrence->status);
    }

    public function test_target_relations(): void
    {
        $reminder = Reminder::factory()->create();
        $user = User::factory()->create();
        $target = ReminderTarget::factory()->create([
            'reminder_id' => $reminder->id,
            'targetable_type' => User::class,
            'targetable_id' => $user->id,
        ]);

        $this->assertTrue($target->reminder->is($reminder));
        $this->assertTrue($target->targetable->is($user));
    }
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi gagal**

Run: `vendor/bin/sail artisan test --compact tests/Unit/Models/ReminderModelTest.php`
Expected: FAIL — class `App\Models\Reminder` not found.

- [ ] **Step 3: Buat model dengan artisan**

Run: `vendor/bin/sail artisan make:model Reminder --no-interaction`
Run: `vendor/bin/sail artisan make:model 'Reminder/ReminderTarget' --no-interaction`
Run: `vendor/bin/sail artisan make:model 'Reminder/ReminderOccurrence' --no-interaction`
Run: `vendor/bin/sail artisan make:factory ReminderFactory --no-interaction`
Run: `vendor/bin/sail artisan make:factory 'Reminder/ReminderTargetFactory' --no-interaction`
Run: `vendor/bin/sail artisan make:factory 'Reminder/ReminderOccurrenceFactory' --no-interaction`

- [ ] **Step 4: Isi model**

`app/Models/Reminder.php`:

```php
<?php

namespace App\Models;

use App\Enums\RecurrenceType;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reminder extends Model
{
    /** @use HasFactory<ReminderFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'farm_id',
        'created_by_type',
        'created_by_id',
        'title',
        'body',
        'starts_at',
        'recurrence',
        'advance_notify_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'recurrence' => 'array',
            'advance_notify_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function creator(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<ReminderTarget,Reminder>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(ReminderTarget::class);
    }

    /**
     * @return HasMany<ReminderOccurrence,Reminder>
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(ReminderOccurrence::class);
    }

    public function isRecurring(): bool
    {
        return $this->recurrenceType() !== RecurrenceType::None;
    }

    public function recurrenceType(): RecurrenceType
    {
        return RecurrenceType::tryFrom($this->recurrence['type'] ?? RecurrenceType::None->value) ?? RecurrenceType::None;
    }
}
```

`app/Models/Reminder/ReminderTarget.php`:

```php
<?php

namespace App\Models\Reminder;

use App\Models\Reminder;
use Database\Factories\Reminder\ReminderTargetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReminderTarget extends Model
{
    /** @use HasFactory<ReminderTargetFactory> */
    use HasFactory;

    protected $fillable = [
        'reminder_id',
        'targetable_type',
        'targetable_id',
    ];

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }

    public function targetable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

`app/Models/Reminder/ReminderOccurrence.php`:

```php
<?php

namespace App\Models\Reminder;

use App\Enums\ReminderStatus;
use App\Models\Reminder;
use Database\Factories\Reminder\ReminderOccurrenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReminderOccurrence extends Model
{
    /** @use HasFactory<ReminderOccurrenceFactory> */
    use HasFactory;

    protected $fillable = [
        'reminder_id',
        'scheduled_at',
        'advance_notify_at',
        'advance_notified_at',
        'notified_at',
        'status',
        'completed_by_type',
        'completed_by_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'advance_notify_at' => 'datetime',
            'advance_notified_at' => 'datetime',
            'notified_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => ReminderStatus::class,
        ];
    }

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }

    public function completer(): MorphTo
    {
        return $this->morphTo();
    }

    public function markDone(string $completerType, int $completerId): void
    {
        $this->update([
            'status' => ReminderStatus::Done,
            'completed_by_type' => $completerType,
            'completed_by_id' => $completerId,
            'completed_at' => now(),
        ]);
    }

    public function markSkipped(): void
    {
        $this->update([
            'status' => ReminderStatus::Skipped,
            'completed_by_type' => null,
            'completed_by_id' => null,
            'completed_at' => now(),
        ]);
    }
}
```

- [ ] **Step 5: Isi factory**

`database/factories/ReminderFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    protected $model = Reminder::class;

    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'created_by_type' => User::class,
            'created_by_id' => User::factory(),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'starts_at' => now()->addDay()->setTime(8, 0),
            'recurrence' => null,
            'advance_notify_minutes' => null,
            'is_active' => true,
        ];
    }

    public function recurring(): static
    {
        return $this->state(fn () => [
            'recurrence' => ['type' => 'weekly', 'days_of_week' => ['mon', 'wed']],
        ]);
    }
}
```

`database/factories/Reminder/ReminderTargetFactory.php`:

```php
<?php

namespace Database\Factories\Reminder;

use App\Models\Reminder;
use App\Models\Reminder\ReminderTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderTarget>
 */
class ReminderTargetFactory extends Factory
{
    protected $model = ReminderTarget::class;

    public function definition(): array
    {
        return [
            'reminder_id' => Reminder::factory(),
            'targetable_type' => User::class,
            'targetable_id' => User::factory(),
        ];
    }
}
```

`database/factories/Reminder/ReminderOccurrenceFactory.php`:

```php
<?php

namespace Database\Factories\Reminder;

use App\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderOccurrence>
 */
class ReminderOccurrenceFactory extends Factory
{
    protected $model = ReminderOccurrence::class;

    public function definition(): array
    {
        return [
            'reminder_id' => Reminder::factory(),
            'scheduled_at' => now()->addDay()->setTime(8, 0),
            'advance_notify_at' => null,
            'advance_notified_at' => null,
            'notified_at' => null,
            'status' => ReminderStatus::Pending,
            'completed_by_type' => null,
            'completed_by_id' => null,
            'completed_at' => null,
        ];
    }
}
```

- [ ] **Step 6: Jalankan test model**

Run: `vendor/bin/sail artisan test --compact tests/Unit/Models/ReminderModelTest.php`
Expected: PASS (4 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Models database/factories tests
git commit -m "feat: model reminder, target, occurrence + factory"
```

---

### Task 4: Recurrence service (penjabaran jadwal)

**Files:**
- Create: `app/Services/ReminderRecurrenceService.php`
- Create: `tests/Unit/Services/ReminderRecurrenceServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\Reminder` (`recurrence` array, `recurrenceType()`), `App\Enums\RecurrenceType`.
- Produces: `ReminderRecurrenceService::nextOccurrenceAfter(Reminder $reminder, CarbonInterface $after): ?CarbonInterface` — mengembalikan waktu occurrence berikutnya setelah `$after`, atau null jika recurrence `none`.
- Produces: `ReminderRecurrenceService::generateOccurrences(Reminder $reminder, CarbonInterface $from, CarbonInterface $until, int $max = 100): array<int, CarbonInterface>` — daftar waktu occurrence antara `$from` dan `$until` (termasuk `$from`), dibatasi `$max`.

**Logika penjabaran:**

- `none` → tidak ada occurrence berikutnya (generate hanya `starts_at` bila dalam rentang).
- `interval` (`every_days` N, min 1) → tambah N hari.
- `weekly` (`days_of_week` array `['mon','tue',...]`) → hari-hari tersebut dalam seminggu.
- `monthly` (`days_of_month` array `[1, 15]`) → tanggal-tanggal tersebut tiap bulan; jika tanggal tidak ada di bulan itu (mis. 31 di Februari), lewati.

- [ ] **Step 1: Tulis test recurrence yang gagal**

`tests/Unit/Services/ReminderRecurrenceServiceTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Farm;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ReminderRecurrenceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReminderRecurrenceServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeReminder(array $recurrence): Reminder
    {
        $farm = Farm::factory()->create();
        $user = User::factory()->create();

        return Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $user->id,
            'starts_at' => Carbon::parse('2026-08-03 08:00:00'),
            'recurrence' => $recurrence,
        ]);
    }

    public function test_none_recurrence_returns_null_next(): void
    {
        $reminder = $this->makeReminder(['type' => 'none']);
        $service = new ReminderRecurrenceService;

        $this->assertNull($service->nextOccurrenceAfter($reminder, Carbon::parse('2026-08-03 08:00:00')));
    }

    public function test_interval_recurrence_adds_days(): void
    {
        $reminder = $this->makeReminder(['type' => 'interval', 'every_days' => 3]);
        $service = new ReminderRecurrenceService;

        $next = $service->nextOccurrenceAfter($reminder, Carbon::parse('2026-08-03 08:00:00'));

        $this->assertSame('2026-08-06 08:00:00', $next?->format('Y-m-d H:i:s'));
    }

    public function test_weekly_recurrence_skips_to_next_matching_day(): void
    {
        $reminder = $this->makeReminder(['type' => 'weekly', 'days_of_week' => ['wed', 'fri']]);
        $service = new ReminderRecurrenceService;

        // Senin 03 Agu 2026 08:00 → Rabu 05 Agu 2026 08:00
        $next = $service->nextOccurrenceAfter($reminder, Carbon::parse('2026-08-03 08:00:00'));

        $this->assertSame('2026-08-05 08:00:00', $next?->format('Y-m-d H:i:s'));
    }

    public function test_monthly_recurrence_skips_to_next_matching_day(): void
    {
        $reminder = $this->makeReminder(['type' => 'monthly', 'days_of_month' => [15, 20]]);
        $service = new ReminderRecurrenceService;

        // 03 Agu → 15 Agu
        $next = $service->nextOccurrenceAfter($reminder, Carbon::parse('2026-08-03 08:00:00'));

        $this->assertSame('2026-08-15 08:00:00', $next?->format('Y-m-d H:i:s'));
    }

    public function test_generate_occurrences_in_range(): void
    {
        $reminder = $this->makeReminder(['type' => 'weekly', 'days_of_week' => ['mon']]);
        $service = new ReminderRecurrenceService;

        $occurrences = $service->generateOccurrences(
            $reminder,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
        );

        $dates = array_map(fn (Carbon $c) => $c->format('Y-m-d'), $occurrences);

        $this->assertContains('2026-08-03', $dates);
        $this->assertContains('2026-08-10', $dates);
        $this->assertContains('2026-08-17', $dates);
        $this->assertContains('2026-08-24', $dates);
        $this->assertContains('2026-08-31', $dates);
    }

    public function test_generate_respects_max_limit(): void
    {
        $reminder = $this->makeReminder(['type' => 'interval', 'every_days' => 1]);
        $service = new ReminderRecurrenceService;

        $occurrences = $service->generateOccurrences(
            $reminder,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2027-08-01'),
            max: 10,
        );

        $this->assertCount(10, $occurrences);
    }
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi gagal**

Run: `vendor/bin/sail artisan test --compact tests/Unit/Services/ReminderRecurrenceServiceTest.php`
Expected: FAIL — class `App\Services\ReminderRecurrenceService` not found.

- [ ] **Step 3: Implementasi service**

```php
<?php

namespace App\Services;

use App\Enums\RecurrenceType;
use App\Models\Reminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\CarbonInterface;

class ReminderRecurrenceService
{
    public function nextOccurrenceAfter(Reminder $reminder, CarbonInterface $after): ?CarbonInterface
    {
        if (! $reminder->isRecurring()) {
            return null;
        }

        $type = $reminder->recurrenceType();
        $current = Carbon::instance($after)->addSecond();

        return match ($type) {
            RecurrenceType::Interval => $this->nextInterval($reminder, $current),
            RecurrenceType::Weekly => $this->nextWeekly($reminder, $current),
            RecurrenceType::Monthly => $this->nextMonthly($reminder, $current),
            default => null,
        };
    }

    /**
     * @return array<int, CarbonInterface>
     */
    public function generateOccurrences(Reminder $reminder, CarbonInterface $from, CarbonInterface $until, int $max = 100): array
    {
        $occurrences = [];

        if (! $reminder->isRecurring()) {
            $start = Carbon::instance($reminder->starts_at);

            if ($start->between($from, $until)) {
                $occurrences[] = $start;
            }

            return $occurrences;
        }

        $cursor = Carbon::instance($reminder->starts_at);
        $from = Carbon::instance($from);
        $until = Carbon::instance($until);

        if ($cursor->lt($from)) {
            $cursor = $this->advanceTo($reminder, $cursor, $from);
        }

        while ($cursor && $cursor->lte($until) && count($occurrences) < $max) {
            if ($cursor->gte($from)) {
                $occurrences[] = $cursor->copy();
            }

            $cursor = $this->nextOccurrenceAfter($reminder, $cursor);
        }

        return $occurrences;
    }

    private function advanceTo(Reminder $reminder, CarbonInterface $cursor, CarbonInterface $from): ?CarbonInterface
    {
        while ($cursor && $cursor->lt($from)) {
            $next = $this->nextOccurrenceAfter($reminder, $cursor);

            if (! $next || $next->lte($cursor)) {
                return null;
            }

            $cursor = $next;
        }

        return $cursor;
    }

    private function nextInterval(Reminder $reminder, CarbonInterface $current): CarbonInterface
    {
        $everyDays = max(1, (int) ($reminder->recurrence['every_days'] ?? 1));

        return $current->copy()->addDays($everyDays);
    }

    private function nextWeekly(Reminder $reminder, CarbonInterface $current): CarbonInterface
    {
        $days = array_map('strtolower', $reminder->recurrence['days_of_week'] ?? []);
        $days = array_values(array_intersect(
            ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            $days,
        ));

        if ($days === []) {
            return $current->copy()->addWeek();
        }

        $weekdays = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 0];

        $currentDay = $current->dayOfWeekIso; // 1 (Senin) - 7 (Minggu)
        $mapped = array_map(fn (string $d) => $weekdays[$d], $days);
        sort($mapped);

        foreach ($mapped as $targetDay) {
            $iso = $targetDay === 0 ? 7 : $targetDay;

            if ($iso >= $currentDay) {
                return $current->copy()->next((int) $iso === 7 ? Carbon::SUNDAY : $iso);
            }
        }

        return $current->copy()->addWeek()->next((int) ($mapped[0] === 0 ? Carbon::SUNDAY : $mapped[0]));
    }

    private function nextMonthly(Reminder $reminder, CarbonInterface $current): CarbonInterface
    {
        $days = array_map('intval', $reminder->recurrence['days_of_month'] ?? []);
        $days = array_values(array_filter($days, fn (int $d) => $d >= 1 && $d <= 31));
        sort($days);

        if ($days === []) {
            return $current->copy()->addMonth();
        }

        $day = (int) $current->format('d');
        $month = $current->copy()->startOfMonth();

        foreach ($days as $targetDay) {
            if ($targetDay > $day) {
                $candidate = $month->copy()->setDay($targetDay);

                if ($candidate->format('m') !== $month->format('m')) {
                    continue; // tanggal tidak valid di bulan ini
                }

                return $candidate->setTimeFrom($current);
            }
        }

        $nextMonth = $month->copy()->addMonth();

        foreach ($days as $targetDay) {
            $candidate = $nextMonth->copy()->setDay($targetDay);

            if ($candidate->format('m') === $nextMonth->format('m')) {
                return $candidate->setTimeFrom($current);
            }
        }

        return $current->copy()->addMonth();
    }
}
```

Catatan implementer: pastikan logika weekly/monthly menghasilkan waktu yang sama persis dengan jam `$after` (08:00 dst). Verifikasi dengan test yang sudah ditulis. Bila ada perilaku `Carbon::next` yang tricky, boleh gunakan pendekatan iterasi hari (`addDay()` loop dengan batas aman, mis. 400 iterasi) yang lebih mudah diverifikasi.

- [ ] **Step 4: Jalankan test recurrence**

Run: `vendor/bin/sail artisan test --compact tests/Unit/Services/ReminderRecurrenceServiceTest.php`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services tests
git commit -m "feat: layanan penjabaran recurrence reminder"
```

---

### Task 5: ReminderTargetResolver (hierarki & resolusi target)

**Files:**
- Create: `app/Services/ReminderTargetResolver.php`
- Create: `tests/Unit/Services/ReminderTargetResolverTest.php`

**Interfaces:**
- Consumes: `App\Models\Farm`, `App\Models\User`, `App\Models\Farm\Staff`.
- Produces: `ReminderTargetResolver` dengan:
  - `const LEVEL_OWNER = 2; const LEVEL_MANAGER = 1; const LEVEL_STAFF = 0;`
  - `levelOf(User|Staff $actor, Farm $farm): int`
  - `canTarget(User|Staff $actor, Farm $farm, User|Staff $candidate): bool`
  - `resolveTargets(User|Staff $actor, Farm $farm, string $mode, array $targetIds = []): array<int, array{type: class-string, id: int}>`
  - `visibleReminderIds(User|Staff $actor): Illuminate\Support\Collection<int, int>` — id reminder yang terlihat oleh actor (sebagai creator atau target).

**Aturan hierarki:** owner(2) ≥ manager(1) ≥ staff(0). Actor hanya bisa menarget role setara atau lebih rendah. Target dibatasi ke farm yang sama. Level actor diambil dari konteks: untuk `User`, lihat pivot `farm_users`; untuk `Staff`, level 0 (staff) dan farm-nya `staff->farm_id`.

- [ ] **Step 1: Tulis test resolver yang gagal**

`tests/Unit/Services/ReminderTargetResolverTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Farm;
use App\Models\Farm\Staff;
use App\Models\User;
use App\Services\ReminderTargetResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReminderTargetResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeFarmWithRoles(array $roles): array
    {
        $farm = Farm::factory()->create();
        $members = [];

        foreach ($roles as $key => $role) {
            $user = User::factory()->create();
            $farm->users()->attach($user->id, ['role' => $role]);
            $members[$key] = $user;
        }

        $staff = Staff::factory()->create(['farm_id' => $farm->id]);
        $members['staff'] = $staff;

        return ['farm' => $farm, ...$members];
    }

    public function test_level_of_user_and_staff(): void
    {
        ['farm' => $farm, 'owner' => $owner, 'manager' => $manager, 'staff' => $staff] = $this->makeFarmWithRoles([
            'owner' => 'owner',
            'manager' => 'manager',
        ]);

        $resolver = new ReminderTargetResolver;

        $this->assertSame(2, $resolver->levelOf($owner, $farm));
        $this->assertSame(1, $resolver->levelOf($manager, $farm));
        $this->assertSame(0, $resolver->levelOf($staff, $farm));
    }

    public function test_owner_can_target_everyone_in_farm(): void
    {
        ['farm' => $farm, 'owner' => $owner, 'manager' => $manager, 'staff' => $staff] = $this->makeFarmWithRoles([
            'owner' => 'owner',
            'manager' => 'manager',
        ]);

        $resolver = new ReminderTargetResolver;

        $this->assertTrue($resolver->canTarget($owner, $farm, $owner));
        $this->assertTrue($resolver->canTarget($owner, $farm, $manager));
        $this->assertTrue($resolver->canTarget($owner, $farm, $staff));
    }

    public function test_manager_cannot_target_owner(): void
    {
        ['farm' => $farm, 'owner' => $owner, 'manager' => $manager] = $this->makeFarmWithRoles([
            'owner' => 'owner',
            'manager' => 'manager',
        ]);

        $resolver = new ReminderTargetResolver;

        $this->assertFalse($resolver->canTarget($manager, $farm, $owner));
        $this->assertTrue($resolver->canTarget($manager, $farm, $manager));
    }

    public function test_staff_can_only_target_other_staff_in_same_farm(): void
    {
        ['farm' => $farm, 'manager' => $manager, 'staff' => $staff] = $this->makeFarmWithRoles([
            'manager' => 'manager',
        ]);
        $otherStaff = Staff::factory()->create(['farm_id' => $farm->id]);
        $otherFarmStaff = Staff::factory()->create(['farm_id' => Farm::factory()->create()->id]);

        $resolver = new ReminderTargetResolver;

        $this->assertTrue($resolver->canTarget($staff, $farm, $staff));
        $this->assertTrue($resolver->canTarget($staff, $farm, $otherStaff));
        $this->assertFalse($resolver->canTarget($staff, $farm, $manager));
        $this->assertFalse($resolver->canTarget($staff, $farm, $otherFarmStaff));
    }

    public function test_resolve_all_includes_everyone_in_farm(): void
    {
        ['farm' => $farm, 'owner' => $owner, 'manager' => $manager, 'staff' => $staff] = $this->makeFarmWithRoles([
            'owner' => 'owner',
            'manager' => 'manager',
        ]);

        $resolver = new ReminderTargetResolver;
        $targets = $resolver->resolveTargets($owner, $farm, 'all');

        $this->assertCount(3, $targets);

        $flattened = array_map(
            fn (array $t): string => $t['type'].':'.$t['id'],
            $targets,
        );

        $this->assertContains(User::class.':'.$owner->id, $flattened);
        $this->assertContains(User::class.':'.$manager->id, $flattened);
        $this->assertContains(Staff::class.':'.$staff->id, $flattened);
    }

    public function test_resolve_specific_filters_by_hierarchy(): void
    {
        ['farm' => $farm, 'owner' => $owner, 'manager' => $manager, 'staff' => $staff] = $this->makeFarmWithRoles([
            'owner' => 'owner',
            'manager' => 'manager',
        ]);

        $resolver = new ReminderTargetResolver;

        // manager mencoba target owner → ditolak
        $targets = $resolver->resolveTargets($manager, $farm, 'specific', [User::class.':'.$owner->id]);

        $this->assertSame([], $targets);
    }
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi gagal**

Run: `vendor/bin/sail artisan test --compact tests/Unit/Services/ReminderTargetResolverTest.php`
Expected: FAIL — class `App\Services\ReminderTargetResolver` not found.

- [ ] **Step 3: Implementasi resolver**

```php
<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Farm\Staff;
use App\Models\User;
use Illuminate\Support\Collection;

class ReminderTargetResolver
{
    public const LEVEL_OWNER = 2;

    public const LEVEL_MANAGER = 1;

    public const LEVEL_STAFF = 0;

    public function levelOf(User|Staff $actor, Farm $farm): int
    {
        if ($actor instanceof Staff) {
            return self::LEVEL_STAFF;
        }

        $role = $farm->users()
            ->wherePivot('user_id', $actor->id)
            ->first()
            ?->pivot
            ?->role;

        return match ($role) {
            'owner' => self::LEVEL_OWNER,
            'manager' => self::LEVEL_MANAGER,
            default => self::LEVEL_STAFF,
        };
    }

    public function canTarget(User|Staff $actor, Farm $farm, User|Staff $candidate): bool
    {
        if (! $this->isInFarm($candidate, $farm)) {
            return false;
        }

        return $this->levelOf($candidate, $farm) <= $this->levelOf($actor, $farm);
    }

    /**
     * @param  list<string>  $targetIds  Format: "App\Models\User:123"
     * @return array<int, array{type: class-string, id: int}>
     */
    public function resolveTargets(User|Staff $actor, Farm $farm, string $mode, array $targetIds = []): array
    {
        return match ($mode) {
            'self' => [
                ['type' => $actor::class, 'id' => $actor->id],
            ],
            'all' => $this->resolveAll($actor, $farm),
            'specific' => $this->resolveSpecific($actor, $farm, $targetIds),
            default => [],
        };
    }

    /**
     * @return Collection<int, int>
     */
    public function visibleReminderIds(User|Staff $actor): Collection
    {
        $created = \App\Models\Reminder::query()
            ->where('created_by_type', $actor::class)
            ->where('created_by_id', $actor->id)
            ->pluck('id');

        $targeted = \App\Models\Reminder\ReminderTarget::query()
            ->where('targetable_type', $actor::class)
            ->where('targetable_id', $actor->id)
            ->pluck('reminder_id');

        return $created->concat($targeted)->unique()->values();
    }

    private function isInFarm(User|Staff $candidate, Farm $farm): bool
    {
        if ($candidate instanceof Staff) {
            return $candidate->farm_id === $farm->id;
        }

        return $farm->users()->where('user_id', $candidate->id)->exists();
    }

    /**
     * @return array<int, array{type: class-string, id: int}>
     */
    private function resolveAll(User|Staff $actor, Farm $farm): array
    {
        $targets = [];

        $farm->users()->get()->each(function (User $user) use (&$targets, $actor, $farm) {
            if ($this->canTarget($actor, $farm, $user)) {
                $targets[] = ['type' => $user::class, 'id' => $user->id];
            }
        });

        $farm->staff()->get()->each(function (Staff $staff) use (&$targets, $actor, $farm) {
            if ($this->canTarget($actor, $farm, $staff)) {
                $targets[] = ['type' => $staff::class, 'id' => $staff->id];
            }
        });

        return $targets;
    }

    /**
     * @param  list<string>  $targetIds
     * @return array<int, array{type: class-string, id: int}>
     */
    private function resolveSpecific(User|Staff $actor, Farm $farm, array $targetIds): array
    {
        $targets = [];

        foreach ($targetIds as $targetId) {
            [$type, $id] = explode(':', $targetId, 2);

            if ($type === Staff::class) {
                $candidate = Staff::query()->find($id);
            } else {
                $candidate = User::query()->find($id);
            }

            if ($candidate && $this->canTarget($actor, $farm, $candidate)) {
                $targets[] = ['type' => $candidate::class, 'id' => $candidate->id];
            }
        }

        return $targets;
    }
}
```

- [ ] **Step 4: Jalankan test resolver**

Run: `vendor/bin/sail artisan test --compact tests/Unit/Services/ReminderTargetResolverTest.php`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services tests
git commit -m "feat: resolver target reminder dengan hierarki role"
```

---

### Task 6: Form Request Store/Update Reminder + ReminderPolicy

**Files:**
- Create: `app/Http/Requests/Reminder/StoreReminderRequest.php`
- Create: `app/Http/Requests/Reminder/UpdateReminderRequest.php`
- Create: `app/Policies/ReminderPolicy.php`
- Create: `tests/Feature/Reminder/ReminderAuthorizationTest.php`

**Interfaces:**
- Consumes: `App\Services\ReminderTargetResolver`, `App\Models\Reminder`.
- Produces: `StoreReminderRequest` — validated fields: `title, body, starts_at, recurrence (nullable array), advance_notify_minutes (nullable int), target_mode, target_ids (nullable array)`; method `targetMode(): string`, `targetIds(): array`, `recurrence(): ?array`.
- Produces: `UpdateReminderRequest` — validated fields: `title, body, starts_at, recurrence (nullable), advance_notify_minutes (nullable)` (tanpa target — target tidak diedit pada rilis ini; jika diubah, reminder dihapus & dibuat ulang).
- Produces: `ReminderPolicy` — `view(User $user, Reminder $reminder): bool`, `update(User $user, Reminder $reminder): bool`, `delete(User $user, Reminder $reminder): bool`. Semua memakai `$reminder->farm_id` dan `created_by`. Method menerima `User|Staff`? — Policy Laravel terikat ke model User via guard `web`. Untuk guard `staff`, gunakan `Gate::forUser` atau cek manual di controller staff.

**Penting — policy untuk dua guard:** Laravel policy default hanya mengenali model `App\Models\User`. Untuk staff, otorisasi `update`/`delete`/`view` dilakukan manual di controller staff dengan helper sederhana (cek `created_by_type === Staff::class && created_by_id === $staff->id`, atau cek keanggotaan target). Policy `ReminderPolicy` difokuskan untuk guard `web` (User), dan staff memakai cek manual yang sama logikanya.

- [ ] **Step 1: Tulis test otorisasi yang gagal**

`tests/Feature/Reminder/ReminderAuthorizationTest.php`:

```php
<?php

namespace Tests\Feature\Reminder;

use App\Models\Farm;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ReminderAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeFarmWithOwnerAndManager(): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        return compact('owner', 'manager', 'farm');
    }

    public function test_creator_can_view_update_delete(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->makeFarmWithOwnerAndManager();
        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $reminder));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $reminder));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $reminder));
    }

    public function test_non_creator_member_cannot_update_or_delete(): void
    {
        ['owner' => $owner, 'manager' => $manager, 'farm' => $farm] = $this->makeFarmWithOwnerAndManager();
        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
        ]);

        $this->assertFalse(Gate::forUser($manager)->allows('update', $reminder));
        $this->assertFalse(Gate::forUser($manager)->allows('delete', $reminder));
    }

    public function test_target_can_view_but_not_update(): void
    {
        ['owner' => $owner, 'manager' => $manager, 'farm' => $farm] = $this->makeFarmWithOwnerAndManager();
        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
        ]);
        $reminder->targets()->create([
            'targetable_type' => User::class,
            'targetable_id' => $manager->id,
        ]);

        $this->assertTrue(Gate::forUser($manager)->allows('view', $reminder));
        $this->assertFalse(Gate::forUser($manager)->allows('update', $reminder));
    }
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Reminder/ReminderAuthorizationTest.php`
Expected: FAIL — policy `App\Policies\ReminderPolicy` not found.

- [ ] **Step 3: Buat policy + daftarkan**

Buat `app/Policies/ReminderPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Reminder;
use App\Models\User;

class ReminderPolicy
{
    public function view(User $user, Reminder $reminder): bool
    {
        if ($this->isCreator($user, $reminder)) {
            return true;
        }

        return $reminder->targets()
            ->where('targetable_type', User::class)
            ->where('targetable_id', $user->id)
            ->exists();
    }

    public function update(User $user, Reminder $reminder): bool
    {
        return $this->isCreator($user, $reminder);
    }

    public function delete(User $user, Reminder $reminder): bool
    {
        return $this->isCreator($user, $reminder);
    }

    private function isCreator(User $user, Reminder $reminder): bool
    {
        return $reminder->created_by_type === User::class
            && $reminder->created_by_id === $user->id;
    }
}
```

Daftarkan di `app/Providers/AppServiceProvider.php` boot():

```php
use App\Models\Reminder;
use App\Policies\ReminderPolicy;

Gate::policy(Reminder::class, ReminderPolicy::class);
```

- [ ] **Step 4: Buat Form Request**

`app/Http/Requests/Reminder/StoreReminderRequest.php`:

```php
<?php

namespace App\Http\Requests\Reminder;

use App\Models\Farm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Farm $farm */
        $farm = $this->route('farm');

        return $farm->users()->where('user_id', $this->user()->id)->exists();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'starts_at' => ['required', 'date', 'after:now'],
            'recurrence' => ['nullable', 'array'],
            'recurrence.type' => ['required_with:recurrence', Rule::in(['none', 'interval', 'weekly', 'monthly'])],
            'recurrence.every_days' => ['required_if:recurrence.type,interval', 'integer', 'min:1'],
            'recurrence.days_of_week' => ['required_if:recurrence.type,weekly', 'array'],
            'recurrence.days_of_week.*' => ['in:mon,tue,wed,thu,fri,sat,sun'],
            'recurrence.days_of_month' => ['required_if:recurrence.type,monthly', 'array'],
            'recurrence.days_of_month.*' => ['integer', 'min:1', 'max:31'],
            'advance_notify_minutes' => ['nullable', 'integer', 'min:1'],
            'target_mode' => ['required', Rule::in(['self', 'all', 'specific'])],
            'target_ids' => ['nullable', 'array'],
            'target_ids.*' => ['string', 'regex:/^(App\\Models\\\\(User|Farm\\\\Staff)):\\d+$/'],
        ];
    }

    public function targetMode(): string
    {
        return $this->validated('target_mode');
    }

    /**
     * @return list<string>
     */
    public function targetIds(): array
    {
        return $this->validated('target_ids', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function recurrence(): ?array
    {
        return $this->validated('recurrence');
    }
}
```

`app/Http/Requests/Reminder/UpdateReminderRequest.php` — authorize cek user adalah creator; rules tanpa target:

```php
<?php

namespace App\Http\Requests\Reminder;

use App\Models\Reminder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Reminder $reminder */
        $reminder = $this->route('reminder');

        return $reminder->created_by_type === User::class
            && $reminder->created_by_id === $this->user()->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'recurrence' => ['nullable', 'array'],
            'recurrence.type' => ['required_with:recurrence', Rule::in(['none', 'interval', 'weekly', 'monthly'])],
            'recurrence.every_days' => ['required_if:recurrence.type,interval', 'integer', 'min:1'],
            'recurrence.days_of_week' => ['required_if:recurrence.type,weekly', 'array'],
            'recurrence.days_of_week.*' => ['in:mon,tue,wed,thu,fri,sat,sun'],
            'recurrence.days_of_month' => ['required_if:recurrence.type,monthly', 'array'],
            'recurrence.days_of_month.*' => ['integer', 'min:1', 'max:31'],
            'advance_notify_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
```

Tambahkan import `App\Models\User` di `UpdateReminderRequest`.

- [ ] **Step 5: Jalankan test otorisasi**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Reminder/ReminderAuthorizationTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests app/Policies app/Providers tests
git commit -m "feat: form request & policy reminder"
```

---

### Task 7: ReminderDispatchService + command reminders:dispatch

**Files:**
- Create: `app/Services/ReminderDispatchService.php`
- Create: `app/Console/Commands/DispatchReminders.php`
- Create: `tests/Feature/Commands/DispatchRemindersTest.php`

**Interfaces:**
- Consumes: `App\Services\ReminderRecurrenceService`, `App\Services\PushNotificationService`, `App\Models\Reminder`, `App\Models\Reminder\ReminderOccurrence`, `App\Enums\ReminderStatus`.
- Produces: `ReminderDispatchService::dispatchDue(): void` — memproses occurrence jatuh tempo (advance + utama) dan menjabarkan occurrence berikutnya.
- Produces: command `reminders:dispatch` dengan signature `reminders:dispatch`; dipanggil dari scheduler tiap menit.

**Alur dispatch:**

1. Advance: `ReminderOccurrence::where('status', 'pending')->whereNotNull('advance_notify_at')->where('advance_notify_at', '<=', now())->whereNull('advance_notified_at')` → untuk tiap: kirim push `"[Judul] (besok)"` atau `"[Judul] (H-1)"` — gunakan body `"Pengingat awal: {body}"` dengan title `"{title} — sebentar lagi"`, lalu set `advance_notified_at = now()`.
2. Utama: `ReminderOccurrence::where('status', 'pending')->where('scheduled_at', '<=', now())->whereNull('notified_at')` → untuk tiap: kirim push ke semua targets (title = reminder title, body = reminder body, url = route detail), set `notified_at = now()`, lalu jika reminder recurring, generate occurrence berikutnya lewat `ReminderRecurrenceService::nextOccurrenceAfter` dan simpan dengan `advance_notify_at` yang dihitung (`scheduled_at - advance_notify_minutes`), dengan guard unique `(reminder_id, scheduled_at)`.
3. Target dengan relasi `targetable` di-load eager; push via `PushNotificationService::sendToUser`.

**Penting — double-send:** dalam satu run command, beberapa occurrence bisa jatuh tempo bersamaan. Proses secara batch dan set `notified_at`/`advance_notified_at` segera setelah kirim. Karena command berjalan tiap menit, race antar dua proses command mungkin terjadi; gunakan `lockForUpdate()` pada query atau `Cache::lock` (opsional, cukup tandai kolom).

**Catatan testing FCM:** `PushNotificationService` punya dependency `Messaging` yang nullable; di test gunakan Mockery seperti `NotifyDailyMonitoringTest`.

- [ ] **Step 1: Tulis test dispatch yang gagal**

`tests/Feature/Commands/DispatchRemindersTest.php`:

```php
<?php

namespace Tests\Feature\Commands;

use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class DispatchRemindersTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeDueReminder(): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        $target = User::factory()->create();
        $farm->users()->attach($target->id, ['role' => 'manager']);

        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'starts_at' => now()->subMinute(),
            'recurrence' => ['type' => 'interval', 'every_days' => 1],
        ]);

        ReminderTarget::factory()->create([
            'reminder_id' => $reminder->id,
            'targetable_type' => User::class,
            'targetable_id' => $target->id,
        ]);

        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->subMinute(),
        ]);

        return compact('reminder', 'target', 'occurrence');
    }

    public function test_dispatch_sends_push_and_generates_next_occurrence(): void
    {
        ['reminder' => $reminder, 'target' => $target, 'occurrence' => $occurrence] = $this->makeDueReminder();

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')->once()->with(
            Mockery::on(fn (User $user) => $user->is($target)),
            $reminder->title,
            $reminder->body,
            Mockery::type('string'),
        );
        $this->app->instance(PushNotificationService::class, $push);

        $this->artisan('reminders:dispatch')->assertExitCode(0);

        $this->assertNotNull($occurrence->fresh()->notified_at);
        $this->assertDatabaseHas('reminder_occurrences', [
            'reminder_id' => $reminder->id,
            'status' => 'pending',
        ]);
        $this->assertSame(2, $reminder->occurrences()->count());
    }

    public function test_dispatch_sends_advance_notification(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        $target = User::factory()->create();
        $farm->users()->attach($target->id, ['role' => 'manager']);

        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'advance_notify_minutes' => 30,
        ]);

        ReminderTarget::factory()->create([
            'reminder_id' => $reminder->id,
            'targetable_type' => User::class,
            'targetable_id' => $target->id,
        ]);

        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addMinutes(29),
            'advance_notify_at' => now()->subMinute(),
        ]);

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')->once();
        $this->app->instance(PushNotificationService::class, $push);

        $this->artisan('reminders:dispatch')->assertExitCode(0);

        $this->assertNotNull($occurrence->fresh()->advance_notified_at);
        $this->assertNull($occurrence->fresh()->notified_at);
    }

    public function test_dispatch_does_not_resend_notified_occurrence(): void
    {
        ['occurrence' => $occurrence] = $this->makeDueReminder();
        $occurrence->update(['notified_at' => now()]);

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldNotReceive('sendToUser');
        $this->app->instance(PushNotificationService::class, $push);

        $this->artisan('reminders:dispatch')->assertExitCode(0);

        $this->assertNotNull($occurrence->fresh()->notified_at);
    }
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Commands/DispatchRemindersTest.php`
Expected: FAIL — command `reminders:dispatch` not defined.

- [ ] **Step 3: Implementasi service + command**

`app/Services/ReminderDispatchService.php`:

```php
<?php

namespace App\Services;

use App\Enums\ReminderStatus;
use App\Models\Reminder\ReminderOccurrence;
use Illuminate\Support\Facades\DB;

class ReminderDispatchService
{
    public function __construct(
        private readonly ReminderRecurrenceService $recurrence,
        private readonly PushNotificationService $push,
    ) {}

    public function dispatchDue(): void
    {
        $this->dispatchAdvanceNotifications();
        $this->dispatchMainNotifications();
    }

    private function dispatchAdvanceNotifications(): void
    {
        ReminderOccurrence::query()
            ->where('status', ReminderStatus::Pending->value)
            ->whereNotNull('advance_notify_at')
            ->where('advance_notify_at', '<=', now())
            ->whereNull('advance_notified_at')
            ->with(['reminder.targets.targetable'])
            ->get()
            ->each(function (ReminderOccurrence $occurrence) {
                $reminder = $occurrence->reminder;

                $this->sendToTargets(
                    $reminder,
                    "{$reminder->title} — sebentar lagi",
                    "Pengingat awal: {$reminder->body}",
                );

                $occurrence->update(['advance_notified_at' => now()]);
            });
    }

    private function dispatchMainNotifications(): void
    {
        ReminderOccurrence::query()
            ->where('status', ReminderStatus::Pending->value)
            ->where('scheduled_at', '<=', now())
            ->whereNull('notified_at')
            ->with(['reminder.targets.targetable'])
            ->get()
            ->each(function (ReminderOccurrence $occurrence) {
                $reminder = $occurrence->reminder;

                $this->sendToTargets(
                    $reminder,
                    $reminder->title,
                    $reminder->body,
                    route('farm.reminders.show', [$reminder->farm_id, $reminder->id]),
                );

                $occurrence->update(['notified_at' => now()]);

                if ($reminder->isRecurring()) {
                    $this->createNextOccurrence($reminder, $occurrence);
                }
            });
    }

    private function createNextOccurrence($reminder, ReminderOccurrence $occurrence): void
    {
        $next = $this->recurrence->nextOccurrenceAfter($reminder, $occurrence->scheduled_at);

        if (! $next) {
            return;
        }

        $alreadyExists = ReminderOccurrence::query()
            ->where('reminder_id', $reminder->id)
            ->where('scheduled_at', $next)
            ->exists();

        if ($alreadyExists) {
            return;
        }

        ReminderOccurrence::query()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => $next,
            'advance_notify_at' => $reminder->advance_notify_minutes
                ? $next->copy()->subMinutes($reminder->advance_notify_minutes)
                : null,
            'status' => ReminderStatus::Pending,
        ]);
    }

    private function sendToTargets($reminder, string $title, string $body, ?string $url = null): void
    {
        foreach ($reminder->targets as $target) {
            $recipient = $target->targetable;

            if ($recipient) {
                $this->push->sendToUser($recipient, $title, $body, $url);
            }
        }
    }
}
```

`app/Console/Commands/DispatchReminders.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\ReminderDispatchService;
use Illuminate\Console\Command;

class DispatchReminders extends Command
{
    protected $signature = 'reminders:dispatch';

    protected $description = 'Kirim push notification untuk reminder yang jatuh tempo';

    public function handle(ReminderDispatchService $dispatch): int
    {
        $dispatch->dispatchDue();

        return self::SUCCESS;
    }
}
```

Daftarkan di scheduler `bootstrap/app.php`:

```php
$schedule->command('reminders:dispatch')->everyMinute();
```

- [ ] **Step 4: Jalankan test dispatch**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Commands/DispatchRemindersTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services app/Console bootstrap tests
git commit -m "feat: dispatch reminder via scheduler tiap menit"
```

---

### Task 8: ReminderController (guard auth) + views

**Files:**
- Create: `app/Http/Controllers/ReminderController.php`
- Create: `routes/reminders.php`
- Create: `resources/views/reminders/index.blade.php`
- Create: `resources/views/reminders/create.blade.php`
- Create: `resources/views/reminders/show.blade.php`
- Create: `resources/views/reminders/edit.blade.php`
- Create: `resources/views/reminders/calendar.blade.php`
- Create: `tests/Feature/Reminder/ReminderCrudTest.php`
- Modify: `routes/web.php`
- Modify: `resources/views/partials/sidebar.blade.php` (tambahkan link Reminder)

**Interfaces:**
- Consumes: `StoreReminderRequest`, `UpdateReminderRequest`, `ReminderTargetResolver`, `ReminderPolicy`, `ReminderRecurrenceService`.
- Produces: route group `farm.reminders.*` (guard auth, verified):
  - `GET /farm/{farm}/reminders` → `index`
  - `GET /farm/{farm}/reminders/create` → `create`
  - `POST /farm/{farm}/reminders` → `store`
  - `GET /farm/{farm}/reminders/calendar` → `calendar`
  - `GET /farm/{farm}/reminders/{reminder}` → `show`
  - `GET /farm/{farm}/reminders/{reminder}/edit` → `edit`
  - `PUT /farm/{farm}/reminders/{reminder}` → `update`
  - `DELETE /farm/{farm}/reminders/{reminder}` → `destroy`
  - `POST /farm/{farm}/reminders/occurrences/{occurrence}/done` → `occurrenceDone`
  - `POST /farm/{farm}/reminders/occurrences/{occurrence}/skip` → `occurrenceSkip`

**Route ordering note:** route `calendar` harus dideklarasikan SEBELUM `{reminder}` karena `{reminder}` adalah model binding yang akan menelan string "calendar". Order: index, create, store, calendar, then `{reminder}` routes.

- [ ] **Step 1: Tulis test CRUD yang gagal**

`tests/Feature/Reminder/ReminderCrudTest.php`:

```php
<?php

namespace Tests\Feature\Reminder;

use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReminderCrudTest extends TestCase
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

    public function test_owner_can_create_reminder_targeting_all(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($owner)->post(route('farm.reminders.store', $farm), [
            'title' => 'Tambah AB Mix',
            'body' => 'Tambahkan AB mix ke tank utama',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
            'target_mode' => 'all',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('reminders', [
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'title' => 'Tambah AB Mix',
        ]);

        $reminder = Reminder::where('farm_id', $farm->id)->firstOrFail();

        $this->assertSame(2, $reminder->targets()->count());
        $this->assertSame(1, $reminder->occurrences()->count());
    }

    public function test_manager_cannot_target_owner(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($manager)->post(route('farm.reminders.store', $farm), [
            'title' => 'Reminder ke owner',
            'body' => 'Tidak boleh',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
            'target_mode' => 'specific',
            'target_ids' => [User::class.':'.$owner->id],
        ]);

        $response->assertSessionHasErrors();

        $this->assertDatabaseMissing('reminders', ['title' => 'Reminder ke owner']);
    }

    public function test_creator_can_edit_reminder(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'starts_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($owner)->put(route('farm.reminders.update', [$farm, $reminder]), [
            'title' => 'Judul Baru',
            'body' => 'Body baru',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reminders', ['id' => $reminder->id, 'title' => 'Judul Baru']);
    }

    public function test_non_creator_cannot_edit_reminder(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $other = User::factory()->create();
        $farm->users()->attach($other->id, ['role' => 'manager']);
        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
        ]);

        $response = $this->actingAs($other)->put(route('farm.reminders.update', [$farm, $reminder]), [
            'title' => 'Ditolak',
            'body' => 'Tidak boleh',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertForbidden();
    }

    public function test_target_can_mark_occurrence_done(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);
        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
        ]);
        ReminderTarget::factory()->create([
            'reminder_id' => $reminder->id,
            'targetable_type' => User::class,
            'targetable_id' => $manager->id,
        ]);
        $occurrence = ReminderOccurrence::factory()->create(['reminder_id' => $reminder->id]);

        $response = $this->actingAs($manager)->post(
            route('farm.reminders.occurrence-done', [$farm, $occurrence]),
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('reminder_occurrences', [
            'id' => $occurrence->id,
            'status' => 'done',
            'completed_by_type' => User::class,
            'completed_by_id' => $manager->id,
        ]);
    }
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Reminder/ReminderCrudTest.php`
Expected: FAIL — route `farm.reminders.store` not defined.

- [ ] **Step 3: Buat controller**

`app/Http/Controllers/ReminderController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reminder\StoreReminderRequest;
use App\Http\Requests\Reminder\UpdateReminderRequest;
use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use App\Services\ReminderRecurrenceService;
use App\Services\ReminderTargetResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ReminderController extends Controller
{
    public function __construct(
        private readonly ReminderTargetResolver $resolver,
        private readonly ReminderRecurrenceService $recurrence,
    ) {}

    public function index(Request $request, Farm $farm): View
    {
        $this->authorize('view', $farm);

        $visibleIds = $this->resolver->visibleReminderIds($request->user());

        $reminders = Reminder::query()
            ->whereIn('id', $visibleIds)
            ->with('targets.targetable')
            ->orderByDesc('starts_at')
            ->get();

        return view('reminders.index', compact('farm', 'reminders'));
    }

    public function create(Request $request, Farm $farm): View
    {
        $this->authorize('view', $farm);

        return view('reminders.create', compact('farm'));
    }

    public function store(StoreReminderRequest $request, Farm $farm): RedirectResponse
    {
        $validated = $request->validated();

        $targets = $this->resolver->resolveTargets(
            $request->user(),
            $farm,
            $request->targetMode(),
            $request->targetIds(),
        );

        if ($targets === []) {
            return back()->withErrors(['target_mode' => 'Tidak ada target yang valid untuk reminder ini.'])
                ->withInput();
        }

        $recurrence = $request->recurrence() ?? ['type' => 'none'];

        $reminder = Reminder::query()->create([
            'farm_id' => $farm->id,
            'created_by_type' => $request->user()::class,
            'created_by_id' => $request->user()->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'starts_at' => $validated['starts_at'],
            'recurrence' => $recurrence,
            'advance_notify_minutes' => $validated['advance_notify_minutes'] ?? null,
        ]);

        foreach ($targets as $target) {
            ReminderTarget::query()->create([
                'reminder_id' => $reminder->id,
                'targetable_type' => $target['type'],
                'targetable_id' => $target['id'],
            ]);
        }

        $startsAt = Carbon::parse($validated['starts_at']);

        ReminderOccurrence::query()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => $startsAt,
            'advance_notify_at' => isset($validated['advance_notify_minutes'])
                ? $startsAt->copy()->subMinutes($validated['advance_notify_minutes'])
                : null,
        ]);

        return redirect()->route('farm.reminders.index', $farm)
            ->with('success', 'Reminder berhasil dibuat.');
    }

    public function show(Request $request, Farm $farm, Reminder $reminder): View
    {
        $this->authorize('view', $reminder);

        $reminder->load(['targets.targetable', 'occurrences']);

        return view('reminders.show', compact('farm', 'reminder'));
    }

    public function edit(Request $request, Farm $farm, Reminder $reminder): View
    {
        Gate::authorize('update', $reminder);

        return view('reminders.edit', compact('farm', 'reminder'));
    }

    public function update(UpdateReminderRequest $request, Farm $farm, Reminder $reminder): RedirectResponse
    {
        $validated = $request->validated();

        $reminder->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'starts_at' => $validated['starts_at'],
            'recurrence' => $validated['recurrence'] ?? ['type' => 'none'],
            'advance_notify_minutes' => $validated['advance_notify_minutes'] ?? null,
        ]);

        // Reset occurrence yang belum dikirim agar mengikuti jadwal baru
        $reminder->occurrences()
            ->whereNull('notified_at')
            ->whereNull('advance_notified_at')
            ->delete();

        $startsAt = Carbon::parse($validated['starts_at']);

        ReminderOccurrence::query()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => $startsAt,
            'advance_notify_at' => isset($validated['advance_notify_minutes'])
                ? $startsAt->copy()->subMinutes($validated['advance_notify_minutes'])
                : null,
        ]);

        return redirect()->route('farm.reminders.show', [$farm, $reminder])
            ->with('success', 'Reminder berhasil diperbarui.');
    }

    public function destroy(Request $request, Farm $farm, Reminder $reminder): RedirectResponse
    {
        Gate::authorize('delete', $reminder);

        $reminder->delete();

        return redirect()->route('farm.reminders.index', $farm)
            ->with('success', 'Reminder berhasil dihapus.');
    }

    public function calendar(Request $request, Farm $farm): View
    {
        $this->authorize('view', $farm);

        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $visibleIds = $this->resolver->visibleReminderIds($request->user());

        $reminders = Reminder::query()->whereIn('id', $visibleIds)->get();

        // Occurrence tersimpan (sudah di-track) dalam rentang bulan
        $stored = ReminderOccurrence::query()
            ->whereIn('reminder_id', $visibleIds)
            ->whereBetween('scheduled_at', [$start, $end])
            ->with('reminder')
            ->get();

        // Occurrence yang belum tersimpan untuk reminder recurring (dijabarkan on-demand)
        $generated = collect();

        foreach ($reminders as $reminder) {
            $generated = $generated->concat(
                $this->recurrence
                    ->generateOccurrences($reminder, $start, $end)
                    ->map(fn (Carbon $c) => (object) [
                        'scheduled_at' => $c,
                        'reminder' => $reminder,
                    ]),
            );
        }

        // Gabungkan, buang duplikat (yang sudah tersimpan), lalu group per tanggal
        $storedKeys = $stored->map(fn ($o) => $o->reminder_id.'|'.$o->scheduled_at->format('Y-m-d H:i'));

        $byDate = $stored
            ->concat($generated->filter(fn ($item) => ! $storedKeys->contains(
                $item->reminder->id.'|'.$item->scheduled_at->format('Y-m-d H:i'),
            )))
            ->groupBy(fn ($item) => $item->scheduled_at->format('Y-m-d'));

        return view('reminders.calendar', compact('farm', 'byDate', 'start', 'month'));
    }

    public function occurrenceDone(Request $request, Farm $farm, ReminderOccurrence $occurrence): RedirectResponse
    {
        $user = $request->user();

        $canComplete = $occurrence->reminder->created_by_type === User::class
            && $occurrence->reminder->created_by_id === $user->id;

        if (! $canComplete) {
            $canComplete = $occurrence->reminder->targets()
                ->where('targetable_type', User::class)
                ->where('targetable_id', $user->id)
                ->exists();
        }

        if (! $canComplete) {
            abort(403);
        }

        $occurrence->markDone(User::class, $user->id);

        return back()->with('success', 'Reminder ditandai selesai.');
    }

    public function occurrenceSkip(Request $request, Farm $farm, ReminderOccurrence $occurrence): RedirectResponse
    {
        $user = $request->user();

        $canSkip = $occurrence->reminder->created_by_type === User::class
            && $occurrence->reminder->created_by_id === $user->id;

        if (! $canSkip) {
            $canSkip = $occurrence->reminder->targets()
                ->where('targetable_type', User::class)
                ->where('targetable_id', $user->id)
                ->exists();
        }

        if (! $canSkip) {
            abort(403);
        }

        $occurrence->markSkipped();

        return back()->with('success', 'Reminder dilewati.');
    }
}
```

Tambahkan `use App\Models\User;` di controller. Import `Illuminate\View\View` sebagai `Illuminate\Contracts\View\View` — cek pola existing (controller lain memakai `Illuminate\Contracts\View\View`).

- [ ] **Step 4: Daftarkan routes**

Buat `routes/reminders.php`:

```php
<?php

use App\Http\Controllers\ReminderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('farm')->as('farm.')->group(function () {
    Route::get('/{farm}/reminders', [ReminderController::class, 'index'])->name('reminders.index');
    Route::get('/{farm}/reminders/create', [ReminderController::class, 'create'])->name('reminders.create');
    Route::post('/{farm}/reminders', [ReminderController::class, 'store'])->name('reminders.store');
    Route::get('/{farm}/reminders/calendar', [ReminderController::class, 'calendar'])->name('reminders.calendar');

    Route::get('/{farm}/reminders/{reminder}', [ReminderController::class, 'show'])->name('reminders.show');
    Route::get('/{farm}/reminders/{reminder}/edit', [ReminderController::class, 'edit'])->name('reminders.edit');
    Route::put('/{farm}/reminders/{reminder}', [ReminderController::class, 'update'])->name('reminders.update');
    Route::delete('/{farm}/reminders/{reminder}', [ReminderController::class, 'destroy'])->name('reminders.destroy');

    Route::post('/{farm}/reminders/occurrences/{occurrence}/done', [ReminderController::class, 'occurrenceDone'])->name('reminders.occurrence-done');
    Route::post('/{farm}/reminders/occurrences/{occurrence}/skip', [ReminderController::class, 'occurrenceSkip'])->name('reminders.occurrence-skip');
});
```

Update `routes/web.php` — tambah `require __DIR__.'/reminders.php';`.

- [ ] **Step 5: Buat views**

Buat `resources/views/reminders/index.blade.php` mengikuti pola `farm-members/index.blade.php` (extend `layouts.app`, sidebar, topbar, tabel, tombol "Buat Reminder" ke `route('farm.reminders.create', $farm)`, link ke calendar). Kolom: Judul, Target (nama-nama dari `targets.targetable`), Waktu (`starts_at->format('d M Y H:i')`), Status, Aksi (Lihat/Edit/Hapus).

Buat `resources/views/reminders/create.blade.php` dengan form:
- `title` (text, required)
- `body` (textarea, required)
- `starts_at` (datetime-local, required)
- `recurrence` — radio/select type: none / interval (input `recurrence[every_days]`) / weekly (checkbox `recurrence[days_of_week][]` senin-minggu) / monthly (input `recurrence[days_of_month][]` dipisah koma)
- `advance_notify_minutes` (select optional: kosong / 30 / 60 / 1440)
- `target_mode` (radio: self / all / specific)
- `target_ids[]` (checkbox daftar user & staff farm yang memenuhi hierarki — hitung via `ReminderTargetResolver` di controller `create()` dan kirim sebagai `$eligibleTargets`)

Catatan implementer: di `create()` controller, siapkan `$eligibleTargets`:

```php
$farm->load('users', 'staff');
$eligible = [];

foreach ($farm->users as $member) {
    if ($this->resolver->canTarget($request->user(), $farm, $member)) {
        $eligible[] = ['id' => $member::class.':'.$member->id, 'name' => $member->name];
    }
}

foreach ($farm->staff as $staff) {
    if ($this->resolver->canTarget($request->user(), $farm, $staff)) {
        $eligible[] = ['id' => $staff::class.':'.$staff->id, 'name' => $staff->name.' (Petugas)'];
    }
}

return view('reminders.create', compact('farm', 'eligible'));
```

Buat `resources/views/reminders/show.blade.php` — detail reminder + daftar occurrence dengan tombol done/skip per occurrence (form POST ke `farm.reminders.occurrence-done`/`occurrence-skip`).

Buat `resources/views/reminders/edit.blade.php` — form edit (title, body, starts_at, recurrence, advance_notify_minutes), POST ke `farm.reminders.update`.

Buat `resources/views/reminders/calendar.blade.php` — grid kalender bulan (`$month`, navigasi prev/next), cell berisi badge reminder untuk tanggal yang punya occurrence. Gunakan `$byDate` (Collection keyed by `Y-m-d`).

- [ ] **Step 6: Tambahkan link sidebar**

Di `resources/views/partials/sidebar.blade.php`, tambahkan item menu "Reminder" dengan route `route('farm.reminders.calendar', $farm)` — cek pola nav existing di file tersebut dan tambahkan sesuai strukturnya.

- [ ] **Step 7: Jalankan test CRUD**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Reminder/ReminderCrudTest.php`
Expected: PASS (6 tests).

- [ ] **Step 8: Pint + commit**

Run: `vendor/bin/sail bin pint --dirty --format agent`
Run: `vendor/bin/sail artisan test --compact tests/Feature/Reminder`
Expected: PASS.

```bash
git add app routes resources tests
git commit -m "feat: controller reminder, routes, dan views"
```

---

### Task 9: Staff reminder (guard staff) + staff push subscription JS

**Files:**
- Create: `app/Http/Controllers/Staff/StaffReminderController.php`
- Modify: `routes/staff.php`
- Create: `resources/views/staff/reminders/index.blade.php`
- Create: `resources/views/staff/reminders/create.blade.php`
- Create: `resources/views/staff/reminders/calendar.blade.php`
- Create: `tests/Feature/Staff/StaffReminderTest.php`
- Modify: `resources/js/firebase.js` (staff push registration)
- Modify: `resources/views/layouts/staff.blade.php` (nav link Reminder + meta csrf sudah ada)

**Interfaces:**
- Consumes: `ReminderTargetResolver`, `ReminderRecurrenceService`, `PushNotificationService`.
- Produces: route group `staff.reminders.*`:
  - `GET /staff/reminders` → `index`
  - `GET /staff/reminders/create` → `create`
  - `POST /staff/reminders` → `store`
  - `GET /staff/reminders/calendar` → `calendar`
  - `POST /staff/reminders/occurrences/{occurrence}/done` → `occurrenceDone`
  - `POST /staff/reminders/occurrences/{occurrence}/skip` → `occurrenceSkip`

**Logika staff:**

- Staff terikat satu farm (`auth('staff')->user()->farm_id`). Semua query reminder dibatasi `farm_id` staff.
- `visibleReminderIds` dipakai untuk index/calendar.
- `create/store`: gunakan `ReminderTargetResolver` — `levelOf(staff, farm) = 0`, sehingga hanya bisa menarget staff lain (dan dirinya). `resolveTargets` dengan mode `all` hanya menghasilkan staff lain + dirinya. `specific` hanya staff yang lolos.
- `occurrenceDone`/`occurrenceSkip`: cek creator (`Staff::class`, id staff) atau target.
- Otorisasi edit/hapus: hanya creator staff itu sendiri. Tidak ada route edit/update staff pada rilis ini (cukup hapus & buat ulang) — namun boleh ditambah jika mengikuti pola staff lain. Untuk kesederhanaan dan konsistensi spec ("hanya pembuat yang bisa edit/hapus"), tambahkan `destroy` untuk staff.

- [ ] **Step 1: Tulis test staff reminder yang gagal**

`tests/Feature/Staff/StaffReminderTest.php`:

```php
<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\Staff;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffReminderTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setUpStaff(): array
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);
        $otherStaff = Staff::factory()->create(['farm_id' => $farm->id]);

        return compact('farm', 'staff', 'otherStaff');
    }

    public function test_staff_can_create_reminder_targeting_self(): void
    {
        ['farm' => $farm, 'staff' => $staff] = $this->setUpStaff();

        $response = $this->actingAs($staff, 'staff')->post(route('staff.reminders.store'), [
            'title' => 'Cek pH',
            'body' => 'Cek pH tank 1',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
            'target_mode' => 'self',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reminders', [
            'farm_id' => $farm->id,
            'created_by_type' => Staff::class,
            'created_by_id' => $staff->id,
            'title' => 'Cek pH',
        ]);
    }

    public function test_staff_can_target_other_staff_in_same_farm(): void
    {
        ['farm' => $farm, 'staff' => $staff, 'otherStaff' => $otherStaff] = $this->setUpStaff();

        $response = $this->actingAs($staff, 'staff')->post(route('staff.reminders.store'), [
            'title' => 'Bantu cek',
            'body' => 'Tolong cek tank',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
            'target_mode' => 'specific',
            'target_ids' => [Staff::class.':'.$otherStaff->id],
        ]);

        $response->assertRedirect();

        $reminder = Reminder::where('title', 'Bantu cek')->firstOrFail();

        $this->assertDatabaseHas('reminder_targets', [
            'reminder_id' => $reminder->id,
            'targetable_type' => Staff::class,
            'targetable_id' => $otherStaff->id,
        ]);
    }

    public function test_staff_cannot_target_manager_user(): void
    {
        ['farm' => $farm, 'staff' => $staff] = $this->setUpStaff();
        $manager = \App\Models\User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($staff, 'staff')->post(route('staff.reminders.store'), [
            'title' => 'Ke manager',
            'body' => 'Tidak boleh',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'recurrence' => ['type' => 'none'],
            'target_mode' => 'specific',
            'target_ids' => [\App\Models\User::class.':'.$manager->id],
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('reminders', ['title' => 'Ke manager']);
    }

    public function test_staff_can_mark_own_reminder_occurrence_done(): void
    {
        ['farm' => $farm, 'staff' => $staff] = $this->setUpStaff();
        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => Staff::class,
            'created_by_id' => $staff->id,
        ]);
        $occurrence = ReminderOccurrence::factory()->create(['reminder_id' => $reminder->id]);

        $response = $this->actingAs($staff, 'staff')->post(
            route('staff.reminders.occurrence-done', $occurrence),
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('reminder_occurrences', [
            'id' => $occurrence->id,
            'status' => 'done',
            'completed_by_type' => Staff::class,
            'completed_by_id' => $staff->id,
        ]);
    }

    public function test_staff_cannot_see_reminder_of_other_farm(): void
    {
        ['farm' => $farm, 'staff' => $staff] = $this->setUpStaff();
        $otherFarm = Farm::factory()->create();
        $otherReminder = Reminder::factory()->create([
            'farm_id' => $otherFarm->id,
            'created_by_type' => Staff::class,
            'created_by_id' => Staff::factory()->create(['farm_id' => $otherFarm->id])->id,
        ]);

        $response = $this->actingAs($staff, 'staff')->get(route('staff.reminders.index'));

        $response->assertOk();
        $response->assertDontSee($otherReminder->title);
    }
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi gagal**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Staff/StaffReminderTest.php`
Expected: FAIL — route `staff.reminders.store` not defined.

- [ ] **Step 3: Implementasi StaffReminderController**

```php
<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Farm\Staff;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use App\Services\ReminderRecurrenceService;
use App\Services\ReminderTargetResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StaffReminderController extends Controller
{
    public function __construct(
        private readonly ReminderTargetResolver $resolver,
        private readonly ReminderRecurrenceService $recurrence,
    ) {}

    public function index(Request $request): View
    {
        $staff = $request->user();
        $visibleIds = $this->resolver->visibleReminderIds($staff);

        $reminders = Reminder::query()
            ->where('farm_id', $staff->farm_id)
            ->whereIn('id', $visibleIds)
            ->with('targets.targetable')
            ->orderByDesc('starts_at')
            ->get();

        return view('staff.reminders.index', compact('reminders'));
    }

    public function create(Request $request): View
    {
        $staff = $request->user();
        $farm = $staff->farm;
        $farm->load('staff');

        $eligible = [];

        foreach ($farm->staff as $candidate) {
            if ($this->resolver->canTarget($staff, $farm, $candidate)) {
                $eligible[] = ['id' => $candidate::class.':'.$candidate->id, 'name' => $candidate->name];
            }
        }

        return view('staff.reminders.create', compact('eligible'));
    }

    public function store(Request $request): RedirectResponse
    {
        $staff = $request->user();
        $farm = $staff->farm;

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'starts_at' => ['required', 'date', 'after:now'],
            'recurrence' => ['nullable', 'array'],
            'recurrence.type' => ['required_with:recurrence', 'in:none,interval,weekly,monthly'],
            'recurrence.every_days' => ['required_if:recurrence.type,interval', 'integer', 'min:1'],
            'recurrence.days_of_week' => ['required_if:recurrence.type,weekly', 'array'],
            'recurrence.days_of_week.*' => ['in:mon,tue,wed,thu,fri,sat,sun'],
            'recurrence.days_of_month' => ['required_if:recurrence.type,monthly', 'array'],
            'recurrence.days_of_month.*' => ['integer', 'min:1', 'max:31'],
            'advance_notify_minutes' => ['nullable', 'integer', 'min:1'],
            'target_mode' => ['required', 'in:self,all,specific'],
            'target_ids' => ['nullable', 'array'],
            'target_ids.*' => ['string', 'regex:/^(App\\Models\\\\(User|Farm\\\\Staff)):\\d+$/'],
        ]);

        $targets = $this->resolver->resolveTargets(
            $staff,
            $farm,
            $validated['target_mode'],
            $validated['target_ids'] ?? [],
        );

        if ($targets === []) {
            return back()->withErrors(['target_mode' => 'Tidak ada target yang valid.'])->withInput();
        }

        $reminder = Reminder::query()->create([
            'farm_id' => $farm->id,
            'created_by_type' => Staff::class,
            'created_by_id' => $staff->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'starts_at' => $validated['starts_at'],
            'recurrence' => $validated['recurrence'] ?? ['type' => 'none'],
            'advance_notify_minutes' => $validated['advance_notify_minutes'] ?? null,
        ]);

        foreach ($targets as $target) {
            ReminderTarget::query()->create([
                'reminder_id' => $reminder->id,
                'targetable_type' => $target['type'],
                'targetable_id' => $target['id'],
            ]);
        }

        $startsAt = Carbon::parse($validated['starts_at']);

        ReminderOccurrence::query()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => $startsAt,
            'advance_notify_at' => isset($validated['advance_notify_minutes'])
                ? $startsAt->copy()->subMinutes($validated['advance_notify_minutes'])
                : null,
        ]);

        return redirect()->route('staff.reminders.index')
            ->with('success', 'Reminder berhasil dibuat.');
    }

    public function calendar(Request $request): View
    {
        $staff = $request->user();
        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $visibleIds = $this->resolver->visibleReminderIds($staff);

        $reminders = Reminder::query()
            ->where('farm_id', $staff->farm_id)
            ->whereIn('id', $visibleIds)
            ->get();

        $stored = ReminderOccurrence::query()
            ->whereIn('reminder_id', $visibleIds)
            ->whereBetween('scheduled_at', [$start, $end])
            ->with('reminder')
            ->get();

        $generated = collect();

        foreach ($reminders as $reminder) {
            $generated = $generated->concat(
                $this->recurrence
                    ->generateOccurrences($reminder, $start, $end)
                    ->map(fn (Carbon $c) => (object) [
                        'scheduled_at' => $c,
                        'reminder' => $reminder,
                    ]),
            );
        }

        // Gabungkan, buang duplikat (yang sudah tersimpan), lalu group per tanggal
        $storedKeys = $stored->map(fn ($o) => $o->reminder_id.'|'.$o->scheduled_at->format('Y-m-d H:i'));

        $byDate = $stored
            ->concat($generated->filter(fn ($item) => ! $storedKeys->contains(
                $item->reminder->id.'|'.$item->scheduled_at->format('Y-m-d H:i'),
            )))
            ->groupBy(fn ($item) => $item->scheduled_at->format('Y-m-d'));

        return view('staff.reminders.calendar', compact('byDate', 'start', 'month'));
    }

    public function occurrenceDone(Request $request, ReminderOccurrence $occurrence): RedirectResponse
    {
        $staff = $request->user();

        if ($occurrence->reminder->farm_id !== $staff->farm_id) {
            abort(403);
        }

        $canComplete = $occurrence->reminder->created_by_type === Staff::class
            && $occurrence->reminder->created_by_id === $staff->id;

        if (! $canComplete) {
            $canComplete = $occurrence->reminder->targets()
                ->where('targetable_type', Staff::class)
                ->where('targetable_id', $staff->id)
                ->exists();
        }

        if (! $canComplete) {
            abort(403);
        }

        $occurrence->markDone(Staff::class, $staff->id);

        return back()->with('success', 'Reminder ditandai selesai.');
    }

    public function occurrenceSkip(Request $request, ReminderOccurrence $occurrence): RedirectResponse
    {
        $staff = $request->user();

        if ($occurrence->reminder->farm_id !== $staff->farm_id) {
            abort(403);
        }

        $canSkip = $occurrence->reminder->created_by_type === Staff::class
            && $occurrence->reminder->created_by_id === $staff->id;

        if (! $canSkip) {
            $canSkip = $occurrence->reminder->targets()
                ->where('targetable_type', Staff::class)
                ->where('targetable_id', $staff->id)
                ->exists();
        }

        if (! $canSkip) {
            abort(403);
        }

        $occurrence->markSkipped();

        return back()->with('success', 'Reminder dilewati.');
    }

    public function destroy(Request $request, Reminder $reminder): RedirectResponse
    {
        $staff = $request->user();

        if ($reminder->farm_id !== $staff->farm_id
            || $reminder->created_by_type !== Staff::class
            || $reminder->created_by_id !== $staff->id) {
            abort(403);
        }

        $reminder->delete();

        return redirect()->route('staff.reminders.index')
            ->with('success', 'Reminder berhasil dihapus.');
    }
}
```

- [ ] **Step 4: Daftarkan routes staff**

Di `routes/staff.php`, dalam group `auth:staff`:

```php
Route::get('/staff/reminders', [StaffReminderController::class, 'index'])->name('staff.reminders.index');
Route::get('/staff/reminders/create', [StaffReminderController::class, 'create'])->name('staff.reminders.create');
Route::post('/staff/reminders', [StaffReminderController::class, 'store'])->name('staff.reminders.store');
Route::get('/staff/reminders/calendar', [StaffReminderController::class, 'calendar'])->name('staff.reminders.calendar');
Route::post('/staff/reminders/occurrences/{occurrence}/done', [StaffReminderController::class, 'occurrenceDone'])->name('staff.reminders.occurrence-done');
Route::post('/staff/reminders/occurrences/{occurrence}/skip', [StaffReminderController::class, 'occurrenceSkip'])->name('staff.reminders.occurrence-skip');
Route::delete('/staff/reminders/{reminder}', [StaffReminderController::class, 'destroy'])->name('staff.reminders.destroy');
```

Import `App\Http\Controllers\Staff\StaffReminderController` di `routes/staff.php`.

- [ ] **Step 5: Buat views staff**

`resources/views/staff/reminders/index.blade.php` — extend `layouts.staff`, tampilkan daftar reminder staff (judul, target, waktu, status, aksi hapus).

`resources/views/staff/reminders/create.blade.php` — form sama seperti versi user tapi extend `layouts.staff`, daftar target dari `$eligible` (hanya staff).

`resources/views/staff/reminders/calendar.blade.php` — grid kalender sederhana.

- [ ] **Step 6: Staff push subscription JS**

`resources/js/firebase.js` — `registerDeviceToken` saat ini POST ke `/push-subscriptions`. Untuk staff, deteksi apakah halaman staff (`document.body.classList` berisi kelas tertentu atau path `/staff/`) dan POST ke `/staff/push-subscriptions`:

```js
const isStaffPage = () => window.location.pathname.startsWith('/staff');

const pushEndpoint = () => (isStaffPage() ? '/staff/push-subscriptions' : '/push-subscriptions');

// ganti semua fetch('/push-subscriptions', ...) dengan fetch(pushEndpoint(), ...)
```

`resources/views/layouts/staff.blade.php` — tambahkan `@vite` sudah ada; tambahkan nav item "Reminder" di `$navs`:

```php
['label' => 'Reminder', 'route' => 'staff.reminders.index', 'active' => ['staff.reminders.index', 'staff.reminders.create', 'staff.reminders.calendar']],
```

- [ ] **Step 7: Jalankan test staff**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Staff/StaffReminderTest.php`
Expected: PASS (5 tests).

- [ ] **Step 8: Pint + commit**

Run: `vendor/bin/sail bin pint --dirty --format agent`
Run: `vendor/bin/sail npm run build` (pastikan JS bundle valid)

```bash
git add app routes resources tests
git commit -m "feat: reminder untuk staff (guard staff)"
```

---

### Task 10: Kalender + final integration test

**Files:**
- Modify: `app/Http/Controllers/ReminderController.php` (kalender — sudah ada di Task 8, verifikasi)
- Create: `tests/Feature/Reminder/ReminderCalendarTest.php`
- Create: `tests/Unit/Services/ReminderDispatchIntegrationTest.php` (opsional — dispatch + kalender)
- Modify: `docs/superpowers/plans/2026-08-03-reminder-feature-plan.md` (update status checkboxes jika perlu)

**Interfaces:**
- Consumes: semua service & controller dari Task 1-9.
- Produces: kalender yang menampilkan occurrence bulan berjalan, hanya reminder visible.

- [x] **Step 1: Tulis test kalender yang gagal**

`tests/Feature/Reminder/ReminderCalendarTest.php`:

```php
<?php

namespace Tests\Feature\Reminder;

use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReminderCalendarTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_calendar_shows_visible_occurrences_for_month(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);

        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'starts_at' => now()->startOfMonth()->addDays(5)->setTime(8, 0),
        ]);

        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => $reminder->starts_at,
        ]);

        $response = $this->actingAs($owner)->get(route('farm.reminders.calendar', $farm));

        $response->assertOk();
        $response->assertSee($reminder->title);
    }

    public function test_calendar_hides_reminder_from_non_creator_non_target(): void
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        $outsider = User::factory()->create();
        $farm->users()->attach($outsider->id, ['role' => 'manager']);

        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
            'starts_at' => now()->startOfMonth()->addDays(5)->setTime(8, 0),
            'title' => 'Reminder Rahasia',
        ]);

        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => $reminder->starts_at,
        ]);

        $response = $this->actingAs($outsider)->get(route('farm.reminders.calendar', $farm));

        $response->assertOk();
        $response->assertDontSee('Reminder Rahasia');
    }
}
```

- [x] **Step 2: Jalankan test kalender**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Reminder/ReminderCalendarTest.php`
Expected: FAIL jika implementasi kalender Task 8 belum benar; PASS setelah diperbaiki.

- [x] **Step 3: Perbaiki implementasi kalender jika perlu**

Pastikan `ReminderController::calendar` menampilkan: semua `stored` occurrence dalam rentang bulan + hasil `generateOccurrences` untuk reminder recurring (yang belum punya occurrence tersimpan), dan hanya untuk reminder di `visibleReminderIds`. Jika test gagal karena `assertDontSee`, cek apakah judul reminder bocor lewat data attribute atau element lain — pastikan hanya reminder visible yang dirender.

- [x] **Step 4: Jalankan seluruh test suite reminder**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Reminder tests/Unit/Services tests/Feature/Commands/DispatchRemindersTest.php tests/Feature/Staff/StaffReminderTest.php tests/Feature/PushSubscription`
Expected: PASS semua.

- [x] **Step 5: Pint seluruh file + jalankan full test suite**

Run: `vendor/bin/sail bin pint --dirty --format agent`
Run: `vendor/bin/sail artisan test --compact`
Expected: PASS (termasuk test existing yang tidak rusak).

- [x] **Step 6: Commit final**

```bash
git add app routes resources tests docs
git commit -m "feat: kalender reminder + integrasi lengkap"
```

---

## Self-Review

### Spec coverage

- ✅ Skema `reminders` + `reminder_targets` + `reminder_occurrences` (Task 1, 3)
- ✅ Push subscription polymorphic untuk staff (Task 2)
- ✅ Hierarki target owner/manager/staff (Task 5)
- ✅ Mode target self/all/specific (Task 5, 8, 9)
- ✅ Dispatch tiap menit, advance + utama, no double-send, next occurrence (Task 7)
- ✅ Tracking done/skipped per occurrence (Task 8, 9)
- ✅ Advance reminder per reminder (Task 7, 8)
- ✅ Kalender bulanan (Task 8, 10)
- ✅ Hanya pembuat yang edit/hapus (Task 6, 8, 9)
- ✅ Route staff + user (Task 8, 9)
- ✅ UI Bahasa Indonesia (semua views)

### Placeholder scan

Tidak ada "TBD"/"TODO". Task 8 Step 5 views ditulis sebagai deskripsi + pola rujukan ke file existing (`farm-members/index.blade.php`) — sengaja demikian karena view adalah file presentasi besar; implementer wajib mengikuti pola yang dirujuk. Semua logika inti (controller, service, request, policy) disertai kode lengkap.

### Type consistency

- `ReminderStatus::values()` dipakai di test Task 1 dan migration Task 1 (default status).
- `ReminderRecurrenceService::nextOccurrenceAfter(Reminder, CarbonInterface): ?CarbonInterface` konsisten dipakai di Task 7 dan Task 8.
- `ReminderTargetResolver::resolveTargets` return `array{type, id}` konsisten di Task 8 dan 9.
- `PushNotificationService::sendToUser(User|Staff, ...)` konsisten dipakai Task 2 dan 7.
- `ReminderOccurrence::markDone/markSkipped` dipakai di Task 8 dan 9.
- Route name konsisten: `farm.reminders.*`, `staff.reminders.*`, `staff.push-subscriptions.*`.
- Form field `recurrence.*` konsisten antara `StoreReminderRequest`, `UpdateReminderRequest`, dan staff controller.
- Format `target_ids` `"Class:id"` konsisten antara resolver, form request regex, controller create (eligible), dan staff.

### Catatan untuk implementer

- Gunakan `vendor/bin/sail` untuk semua perintah.
- Jalankan `vendor/bin/sail bin pint --dirty --format agent` setelah mengubah file PHP.
- Bila timestamps migrasi bentrok, sesuaikan; urutan migrasi penting: reminders → reminder_targets → reminder_occurrences → modify_push_subscriptions.
- Staff guard memakai `auth('staff')` / `$request->user()` dengan guard staff — di controller staff, `$request->user()` otomatis mengembalikan `Staff`.
