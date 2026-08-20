# Reminder Upcoming-Filter & Detail Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Filter halaman global "Pengingat" agar hanya menampilkan reminder yang belum ter-notify (dengan window kemunculan ulang untuk yang berulang), buat card reminder bisa diklik ke halaman detail baru, dan izinkan tambah pengingat dari halaman global via dialog pilih farm.

**Architecture:** Server-side filter ditambahkan sebagai query scope `upcoming()` pada model `Reminder` dan diaktifkan lewat param `?upcoming=1` di `GET /api/v1/reminders`. Endpoint baru `POST /reminders/{reminder}/occurrences/{occurrence}/done|skip` untuk user (otorisasi = pembuat ATAU target, meniru pola Staff). Frontend: hook `useGlobalReminders` menambahkan `upcoming: 1`, card navigasi ke route baru `/farms/$farmId/reminders/$reminderId`, dan dialog pilih farm lalu `ReminderForm` untuk alur tambah.

**Tech Stack:** Laravel 13 + Sanctum (API), PHP 8.4, PHPUnit 12, Sail, Pint. React 19 + TanStack Router + TanStack Query + Tailwind 4 (web). UI copy dalam Bahasa Indonesia.

## Global Constraints

- **Visibilitas filter `upcoming`:** reminder tampil jika (a) `is_active = true`, (b) ada occurrence `status = pending` dengan `notified_at` dan `advance_notified_at` keduanya `NULL`, dan (c) (belum ada occurrence yang pernah ter-notify ATAU occurrence pending itu `scheduled_at <= now + reappear_days`).
- **`reappear_days`:** diambil dari `config('reminders.reappear_days')`, default `2`, di-`env('REMINDER_REAPPEAR_DAYS', 2)`.
- **Non-berulang:** tampil sejak dibuat, hilang setelah ter-notify. **Berulang:** tampil sejak dibuat, hilang setelah ter-notify, muncul lagi `reappear_days` hari sebelum occurrence berikutnya.
- **Halaman per-farm (`/farms/$farmId/reminders`) tetap menampilkan SEMUA reminder** (manajemen); HANYA halaman global `/reminders` yang difilter `upcoming`.
- **Aksi done/skip (user):** otorisasi = pembuat reminder ATAU target user. Selain itu 403. Occurrence milik reminder lain → 404.
- **Halaman detail** = `/farms/$farmId/reminders/$reminderId` (detail + daftar occurrence + tombol Selesai/Lewati per occurrence pending + Edit + Hapus).
- **Alur tambah di halaman global:** dialog "Pilih Farm" dulu, lalu `ReminderForm` (tidak menumpuk Dialog di dalam Dialog).
- **Card clickable** di GlobalReminderList dan ReminderList → navigasi ke halaman detail.
- **API:** pagination 30 (tetap). Response memakai pola `successResponse` / `paginatedResponse` yang sudah ada di `Controller`.
- **Setelah mengubah file PHP:** jalankan `vendor/bin/sail bin pint --format agent` (format file dirty), lalu jalankan test terkait.
- **Setelah mengubah file web:** jalankan `npm run build` (tsc -b && vite build) dan `npm run lint` (oxlint).
- Dua repo git terpisah: API = `hydroponics-api/`, Web = `hydroponics-web/`. Commit di repo masing-masing.
- UI copy harus Bahasa Indonesia (konsisten dengan UI yang ada).

---

### Task 1: API — Config `reappear_days` + scope `upcoming` + filter controller

**Files:**
- Modify: `config/app.php` (tambahkan blok `'reminders'`)
- Modify: `app/Models/Reminder.php` (import + scope `upcoming`)
- Modify: `app/Http/Controllers/ReminderController.php:27-47` (aktifkan filter di `index`)
- Test: `tests/Feature/Reminder/ReminderUpcomingFilterTest.php`

**Interfaces:**
- Consumes: model `Reminder` (factory state `recurring()`), `ReminderOccurrence` factory, `ReminderStatus` enum, `Controller::paginatedResponse`.
- Produces: scope `Reminder::scopeUpcoming(\Illuminate\Database\Eloquent\Builder $query): Builder`; `GET /api/v1/reminders?upcoming=1` mengembalikan hanya reminder yang memenuhi aturan visibilitas di Global Constraints.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/Reminder/ReminderUpcomingFilterTest.php`:

```php
<?php

namespace Tests\Feature\Reminder;

use App\Enums\ReminderStatus;
use App\Http\Controllers\ReminderController;
use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReminderUpcomingFilterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $owner;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->farm = Farm::factory()->create(['created_by' => $this->owner->id]);
        $this->farm->users()->attach($this->owner->id, ['role' => 'owner']);

        Route::middleware(SubstituteBindings::class)->group(function () {
            Route::prefix('api/v1')->group(function () {
                Route::apiResource('reminders', ReminderController::class);
            });
        });
    }

    private function makeReminder(array $attributes = []): Reminder
    {
        return Reminder::factory()->create(array_merge([
            'farm_id' => $this->farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $this->owner->id,
        ], $attributes));
    }

    public function test_upcoming_includes_first_cycle_reminder_before_notification(): void
    {
        $this->makeReminder(['starts_at' => now()->addDays(10), 'title' => 'Siklus Pertama']);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reminders?upcoming=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Siklus Pertama');
    }

    public function test_upcoming_hides_non_recurring_reminder_after_notified(): void
    {
        $reminder = $this->makeReminder(['starts_at' => now()->addDay(), 'title' => 'Sudah Kirim']);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDay(),
            'notified_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reminders?upcoming=1');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_upcoming_includes_recurring_reminder_when_next_occurrence_within_window(): void
    {
        $reminder = $this->makeReminder(['recurrence' => ['type' => 'weekly', 'days_of_week' => ['mon']]]);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->subWeek(),
            'notified_at' => now()->subWeek(),
        ]);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reminders?upcoming=1');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_upcoming_hides_recurring_reminder_when_next_occurrence_beyond_window(): void
    {
        $reminder = $this->makeReminder(['recurrence' => ['type' => 'weekly', 'days_of_week' => ['mon']]]);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->subWeek(),
            'notified_at' => now()->subWeek(),
        ]);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDays(10),
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reminders?upcoming=1');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_upcoming_hides_inactive_reminder(): void
    {
        $this->makeReminder(['starts_at' => now()->addDay(), 'is_active' => false]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reminders?upcoming=1');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_upcoming_hides_reminder_with_only_done_or_skipped_occurrences(): void
    {
        $reminder = $this->makeReminder(['starts_at' => now()->addDay()]);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDay(),
            'status' => ReminderStatus::Done,
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reminders?upcoming=1');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_index_without_upcoming_param_still_returns_all_visible(): void
    {
        $reminder = $this->makeReminder(['starts_at' => now()->addDay()]);
        ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDay(),
            'notified_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reminders');

        $response->assertOk()->assertJsonCount(1, 'data');
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/sail artisan test --compact --filter=ReminderUpcomingFilterTest`
Expected: FAIL — response berisi reminder yang tidak difilter (mis. `test_upcoming_hides_non_recurring_reminder_after_notified` gagal karena masih ada 1 data).

- [ ] **Step 3: Tambahkan konfigurasi `reminders.reappear_days`**

Di `config/app.php`, tambahkan blok baru sebelum baris `'aliases'` (atau setelah array utama, sebagai elemen root baru):

```php
    'reminders' => [
        'reappear_days' => (int) env('REMINDER_REAPPEAR_DAYS', 2),
    ],
```

- [ ] **Step 4: Implementasi scope `upcoming` di model `Reminder`**

Di `app/Models/Reminder.php`, tambahkan import:

```php
use App\Enums\ReminderStatus;
use Illuminate\Database\Eloquent\Builder;
```

Tambahkan method scope di dalam class `Reminder` (setelah `recurrenceType()`):

```php
    public function scopeUpcoming(Builder $query): Builder
    {
        $window = now()->addDays((int) config('reminders.reappear_days', 2));

        return $query
            ->where('is_active', true)
            ->whereHas('occurrences', function (Builder $q) {
                $q->where('status', ReminderStatus::Pending->value)
                    ->whereNull('notified_at')
                    ->whereNull('advance_notified_at');
            })
            ->where(function (Builder $q) use ($window) {
                $q->whereDoesntHave('occurrences', function (Builder $oq) {
                    $oq->whereNotNull('notified_at')
                        ->orWhereNotNull('advance_notified_at');
                })
                ->orWhereHas('occurrences', function (Builder $oq) use ($window) {
                    $oq->where('status', ReminderStatus::Pending->value)
                        ->whereNull('notified_at')
                        ->whereNull('advance_notified_at')
                        ->where('scheduled_at', '<=', $window);
                });
            });
    }
```

- [ ] **Step 5: Aktifkan filter di `ReminderController@index`**

Di `app/Http/Controllers/ReminderController.php`, di dalam `index()`, setelah blok `if ($farmId) { ... }` dan sebelum `$reminders = $query->paginate(30);`, tambahkan:

```php
        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }
```

- [ ] **Step 6: Jalankan test, pastikan lolos**

Run: `vendor/bin/sail artisan test --compact --filter=ReminderUpcomingFilterTest`
Expected: PASS (7 test).

- [ ] **Step 7: Format & commit**

Run: `vendor/bin/sail bin pint --format agent`

```bash
git -C /home/ali/Documents/Work/Agriculture/Hydroponics/hydroponics-api add config/app.php app/Models/Reminder.php app/Http/Controllers/ReminderController.php tests/Feature/Reminder/ReminderUpcomingFilterTest.php
git -C /home/ali/Documents/Work/Agriculture/Hydroponics/hydroponics-api commit -m "feat: add upcoming filter for reminders"
```

---

### Task 2: API — Endpoint user `done`/`skip` occurrence

**Files:**
- Modify: `app/Http/Controllers/ReminderController.php` (tambahkan `occurrenceDone`, `occurrenceSkip`, `authorizeOccurrence`, import `User`)
- Modify: `routes/api.php:109` (tambahkan 2 route di grup user)
- Test: `tests/Feature/Reminder/ReminderOccurrenceActionTest.php`

**Interfaces:**
- Consumes: `ReminderOccurrence::markDone(string $completerType, int $completerId)` dan `markSkipped()` (sudah ada), `ReminderTarget` (via `$reminder->targets()`).
- Produces: `POST /api/v1/reminders/{reminder}/occurrences/{occurrence}/done` dan `.../skip` (auth user). Pembuat ATAU target user → 200 `{success: true}`; lainnya → 403; occurrence milik reminder lain → 404. Frontend memanggil keduanya dari halaman detail.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/Reminder/ReminderOccurrenceActionTest.php`:

```php
<?php

namespace Tests\Feature\Reminder;

use App\Http\Controllers\ReminderController;
use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReminderOccurrenceActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $owner;

    private User $manager;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->manager = User::factory()->create();
        $this->farm = Farm::factory()->create(['created_by' => $this->owner->id]);
        $this->farm->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->farm->users()->attach($this->manager->id, ['role' => 'manager']);

        Route::middleware(SubstituteBindings::class)->group(function () {
            Route::prefix('api/v1')->group(function () {
                Route::apiResource('reminders', ReminderController::class);
                Route::post('reminders/{reminder}/occurrences/{occurrence}/done', [ReminderController::class, 'occurrenceDone']);
                Route::post('reminders/{reminder}/occurrences/{occurrence}/skip', [ReminderController::class, 'occurrenceSkip']);
            });
        });
    }

    private function makeReminder(int $createdById): Reminder
    {
        return Reminder::factory()->create([
            'farm_id' => $this->farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $createdById,
        ]);
    }

    public function test_target_can_mark_occurrence_done(): void
    {
        $reminder = $this->makeReminder($this->owner->id);
        $reminder->targets()->create([
            'targetable_type' => User::class,
            'targetable_id' => $this->manager->id,
        ]);
        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/v1/reminders/{$reminder->id}/occurrences/{$occurrence->id}/done");

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('reminder_occurrences', [
            'id' => $occurrence->id,
            'status' => 'done',
            'completed_by_type' => User::class,
            'completed_by_id' => $this->manager->id,
        ]);
    }

    public function test_creator_can_skip_occurrence(): void
    {
        $reminder = $this->makeReminder($this->owner->id);
        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/reminders/{$reminder->id}/occurrences/{$occurrence->id}/skip");

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('reminder_occurrences', [
            'id' => $occurrence->id,
            'status' => 'skipped',
        ]);
    }

    public function test_non_creator_non_target_cannot_mark_occurrence(): void
    {
        $outsider = User::factory()->create();
        $this->farm->users()->attach($outsider->id, ['role' => 'manager']);
        $reminder = $this->makeReminder($this->owner->id);
        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($outsider)
            ->postJson("/api/v1/reminders/{$reminder->id}/occurrences/{$occurrence->id}/done");

        $response->assertForbidden();
    }

    public function test_occurrence_of_other_reminder_rejected(): void
    {
        $reminder = $this->makeReminder($this->owner->id);
        $other = $this->makeReminder($this->owner->id);
        $occurrence = ReminderOccurrence::factory()->create([
            'reminder_id' => $other->id,
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/reminders/{$reminder->id}/occurrences/{$occurrence->id}/done");

        $response->assertNotFound();
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/sail artisan test --compact --filter=ReminderOccurrenceActionTest`
Expected: FAIL — route 404 (method belum ada di controller).

- [ ] **Step 3: Implementasi action di controller**

Di `app/Http/Controllers/ReminderController.php`, tambahkan import `use App\Models\User;`. Tambahkan method berikut di dalam class (setelah `destroy`):

```php
    public function occurrenceDone(Request $request, Reminder $reminder, ReminderOccurrence $occurrence): JsonResponse
    {
        $this->authorizeOccurrence($request->user(), $reminder, $occurrence);

        $occurrence->markDone($request->user()::class, $request->user()->id);

        return $this->successResponse(['occurrence' => $occurrence->refresh()], 'Occurrence ditandai selesai.');
    }

    public function occurrenceSkip(Request $request, Reminder $reminder, ReminderOccurrence $occurrence): JsonResponse
    {
        $this->authorizeOccurrence($request->user(), $reminder, $occurrence);

        $occurrence->markSkipped();

        return $this->successResponse(['occurrence' => $occurrence->refresh()], 'Occurrence dilewati.');
    }

    private function authorizeOccurrence(User $user, Reminder $reminder, ReminderOccurrence $occurrence): void
    {
        if ($occurrence->reminder_id !== $reminder->id) {
            abort(404);
        }

        $isCreator = $reminder->created_by_type === User::class
            && $reminder->created_by_id === $user->id;

        $isTarget = $reminder->targets()
            ->where('targetable_type', User::class)
            ->where('targetable_id', $user->id)
            ->exists();

        if (! $isCreator && ! $isTarget) {
            abort(403);
        }
    }
```

- [ ] **Step 4: Daftarkan route**

Di `routes/api.php`, dalam grup `Route::middleware(['auth:sanctum', 'user'])`, tepat setelah `Route::apiResource('reminders', ReminderController::class);` (baris 109), tambahkan:

```php
        Route::post('reminders/{reminder}/occurrences/{occurrence}/done', [ReminderController::class, 'occurrenceDone']);
        Route::post('reminders/{reminder}/occurrences/{occurrence}/skip', [ReminderController::class, 'occurrenceSkip']);
```

- [ ] **Step 5: Jalankan test, pastikan lolos**

Run: `vendor/bin/sail artisan test --compact --filter=ReminderOccurrenceActionTest`
Expected: PASS (4 test).

- [ ] **Step 6: Format & commit**

Run: `vendor/bin/sail bin pint --format agent`

```bash
git -C /home/ali/Documents/Work/Agriculture/Hydroponics/hydroponics-api add app/Http/Controllers/ReminderController.php routes/api.php tests/Feature/Reminder/ReminderOccurrenceActionTest.php
git -C /home/ali/Documents/Work/Agriculture/Hydroponics/hydroponics-api commit -m "feat: add user done/skip actions for reminder occurrences"
```

---

### Task 3: Web — Tipe & hook reminders

**Files:**
- Modify: `src/features/reminders/types.ts`
- Modify: `src/features/reminders/hooks/useReminders.ts`

**Interfaces:**
- Consumes: `PaginatedResponse<T>`, `ApiResponse<T>` dari `src/lib/types`.
- Produces: tipe `ReminderOccurrence`, `OccurrenceStatus`; hook `useReminder(id)`, `useOccurrenceDone()`, `useOccurrenceSkip()`; `useGlobalReminders(page)` kini memanggil `?upcoming=1`. Task 4 dan 5 mengonsumsi semua ini.

- [ ] **Step 1: Tambah tipe occurrence**

Di `src/features/reminders/types.ts`, tambahkan di atas `export interface Reminder`:

```ts
export type OccurrenceStatus = 'pending' | 'done' | 'skipped'

export interface ReminderOccurrence {
  id: number
  reminder_id: number
  scheduled_at: string
  advance_notify_at: string | null
  advance_notified_at: string | null
  notified_at: string | null
  status: OccurrenceStatus
  completed_at: string | null
}
```

Ubah baris `occurrences?: unknown[]` menjadi:

```ts
  occurrences?: ReminderOccurrence[]
```

- [ ] **Step 2: Update `useGlobalReminders` + tambah hook baru**

Di `src/features/reminders/hooks/useReminders.ts`, ubah `useGlobalReminders` agar memfilter upcoming:

```ts
export function useGlobalReminders(page = 1) {
  return useQuery({
    queryKey: ['reminders', 'all', 'upcoming', page],
    queryFn: () =>
      api.get<PaginatedResponse<Reminder>>('/reminders', {
        params: { page, upcoming: 1 },
      }),
  })
}
```

Update import tipe (baris 4) dan tambahkan hook berikut di akhir file:

```ts
import type { CreateReminderInput, Reminder, ReminderOccurrence, UpdateReminderInput } from '../types'

export function useReminder(id: number) {
  return useQuery({
    queryKey: ['reminders', 'detail', id],
    queryFn: () =>
      api
        .get<ApiResponse<{ reminder: Reminder }>>(`/reminders/${id}`)
        .then((r) => r.data.reminder),
    enabled: !!id,
  })
}

export function useOccurrenceDone() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ reminderId, occurrenceId }: { reminderId: number; occurrenceId: number }) =>
      api.post<ApiResponse<{ occurrence: ReminderOccurrence }>>(
        `/reminders/${reminderId}/occurrences/${occurrenceId}/done`,
      ),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['reminders'] })
    },
  })
}

export function useOccurrenceSkip() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ reminderId, occurrenceId }: { reminderId: number; occurrenceId: number }) =>
      api.post<ApiResponse<{ occurrence: ReminderOccurrence }>>(
        `/reminders/${reminderId}/occurrences/${occurrenceId}/skip`,
      ),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['reminders'] })
    },
  })
}
```

- [ ] **Step 3: Build & lint**

Run (di `hydroponics-web`): `npm run build && npm run lint`
Expected: tanpa error TypeScript maupun lint.

- [ ] **Step 4: Commit**

```bash
git -C /home/ali/Documents/Work/Agriculture/Hydroponics/hydroponics-web add src/features/reminders/types.ts src/features/reminders/hooks/useReminders.ts
git -C /home/ali/Documents/Work/Agriculture/Hydroponics/hydroponics-web commit -m "feat: add reminder occurrence types and hooks"
```

---

### Task 4: Web — Halaman global: card clickable + alur tambah via dialog pilih farm

**Files:**
- Create: `src/features/reminders/components/ReminderFarmPickerDialog.tsx`
- Modify: `src/features/reminders/components/GlobalReminderList.tsx`

**Interfaces:**
- Consumes: `useGlobalReminders` (Task 3), `useFarms` dari `src/features/farms/hooks/useFarms`, `ReminderForm`, `useNavigate`, `Dialog` UI.
- Produces: `ReminderFarmPickerDialog({ open, onOpenChange, onSelect }: { open: boolean; onOpenChange: (open: boolean) => void; onSelect: (farmId: number) => void })`; `GlobalReminderList` dengan tombol "Tambah Pengingat", card clickable menuju `/farms/$farmId/reminders/$reminderId`, dan alur picker → `ReminderForm`.

- [ ] **Step 1: Buat komponen `ReminderFarmPickerDialog`**

Buat `src/features/reminders/components/ReminderFarmPickerDialog.tsx`:

```tsx
import { Warehouse } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '../../../components/ui/dialog'
import { useFarms } from '../../farms/hooks/useFarms'

export function ReminderFarmPickerDialog({
  open,
  onOpenChange,
  onSelect,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSelect: (farmId: number) => void
}) {
  const { data: farms } = useFarms()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        showCloseButton={false}
        className="bottom-0 top-auto left-1/2 -translate-x-1/2 translate-y-0 rounded-b-none sm:bottom-auto sm:top-1/2 sm:-translate-y-1/2 sm:rounded-b-xl"
      >
        <DialogHeader>
          <DialogTitle>Pilih Farm</DialogTitle>
          <DialogDescription>Pilih farm untuk pengingat baru.</DialogDescription>
        </DialogHeader>
        <div className="grid gap-2">
          {(farms ?? []).map((farm) => (
            <button
              key={farm.id}
              type="button"
              onClick={() => onSelect(farm.id)}
              className="flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 text-left transition-colors hover:bg-secondary/10"
            >
              <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-secondary/15 text-secondary">
                <Warehouse className="size-5" />
              </span>
              <span className="text-sm font-semibold text-foreground">{farm.name}</span>
            </button>
          ))}
        </div>
      </DialogContent>
    </Dialog>
  )
}
```

- [ ] **Step 2: Update `GlobalReminderList`**

Tulis ulang `src/features/reminders/components/GlobalReminderList.tsx` menjadi:

```tsx
import { useState } from 'react'
import { useNavigate } from '@tanstack/react-router'
import type { Reminder } from '../types'
import { useGlobalReminders } from '../hooks/useReminders'
import { Button } from '../../../components/ui/button'
import { Card, CardContent } from '../../../components/ui/card'
import { ReminderForm } from './ReminderForm'
import { ReminderFarmPickerDialog } from './ReminderFarmPickerDialog'

function formatDate(value: string): string {
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function GlobalReminderList() {
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const { data, isLoading, isError, isFetching } = useGlobalReminders(page)
  const [pickerOpen, setPickerOpen] = useState(false)
  const [formOpen, setFormOpen] = useState(false)
  const [formFarmId, setFormFarmId] = useState<number | null>(null)

  if (isLoading) return <p className="text-sm text-muted-foreground">Memuat pengingat...</p>
  if (isError) return <p className="text-sm text-red-600">Gagal memuat pengingat.</p>

  const reminders = data?.data ?? []
  const meta = data?.meta

  return (
    <div className="space-y-3">
      <div className="flex justify-end">
        <Button onClick={() => setPickerOpen(true)}>Tambah Pengingat</Button>
      </div>

      {reminders.length === 0 ? (
        <p className="text-sm text-muted-foreground">Belum ada pengingat.</p>
      ) : (
        reminders.map((reminder: Reminder) => (
          <Card
            key={reminder.id}
            className="cursor-pointer transition-colors hover:bg-secondary/5"
            onClick={() =>
              navigate({
                to: '/farms/$farmId/reminders/$reminderId',
                params: { farmId: String(reminder.farm_id), reminderId: String(reminder.id) },
              })
            }
          >
            <CardContent className="flex items-start justify-between gap-3">
              <div className="space-y-1">
                <div className="flex flex-wrap items-center gap-2">
                  <p className="font-medium">{reminder.title}</p>
                  {reminder.farm && (
                    <span className="rounded-full bg-secondary/15 px-3 py-1 text-xs font-semibold tracking-[0.24em] text-secondary uppercase">
                      {reminder.farm.name}
                    </span>
                  )}
                </div>
                {reminder.body && (
                  <p className="text-sm text-muted-foreground">{reminder.body}</p>
                )}
                <p className="text-xs text-muted-foreground">{formatDate(reminder.starts_at)}</p>
              </div>
            </CardContent>
          </Card>
        ))
      )}

      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <Button
            variant="outline"
            size="sm"
            disabled={page <= 1 || isFetching}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
          >
            Sebelumnya
          </Button>
          <span className="text-muted-foreground">
            Halaman {meta.current_page} dari {meta.last_page}
          </span>
          <Button
            variant="outline"
            size="sm"
            disabled={page >= meta.last_page || isFetching}
            onClick={() => setPage((p) => p + 1)}
          >
            Berikutnya
          </Button>
        </div>
      )}

      <ReminderFarmPickerDialog
        open={pickerOpen}
        onOpenChange={setPickerOpen}
        onSelect={(farmId) => {
          setPickerOpen(false)
          setFormFarmId(farmId)
          setFormOpen(true)
        }}
      />
      {formFarmId !== null && (
        <ReminderForm open={formOpen} onOpenChange={setFormOpen} farmId={formFarmId} />
      )}
    </div>
  )
}
```

- [ ] **Step 3: Build & lint**

Run (di `hydroponics-web`): `npm run build && npm run lint`
Expected: tanpa error. Catatan: route `/farms/$farmId/reminders/$reminderId` belum ada sampai Task 5; TypeScript akan error "no route found". Bila error itu muncul, jalankan `npm run build` setelah Task 5 selesai (atau tambahkan route di Task 5 lalu verifikasi di sini). Untuk menjaga tiap task punya deliverable yang lolos, verifikasi build dilakukan di Step 3 Task 5.

- [ ] **Step 4: Commit**

```bash
git -C /home/ali/Documents/Work/Agriculture/Hydroponics/hydroponics-web add src/features/reminders/components/ReminderFarmPickerDialog.tsx src/features/reminders/components/GlobalReminderList.tsx
git -C /home/ali/Documents/Work/Agriculture/Hydroponics/hydroponics-web commit -m "feat: clickable global reminder cards with farm-picker create flow"
```

---

### Task 5: Web — Halaman detail reminder + card farm list clickable

**Files:**
- Move: `src/routes/_authenticated/farms/$farmId/reminders.tsx` → `src/routes/_authenticated/farms/$farmId/reminders/index.tsx`
- Create: `src/routes/_authenticated/farms/$farmId/reminders/$reminderId.tsx`
- Create: `src/features/reminders/components/ReminderDetail.tsx`
- Modify: `src/features/reminders/components/ReminderList.tsx` (card clickable)

**Interfaces:**
- Consumes: `useReminder`, `useOccurrenceDone`, `useOccurrenceSkip`, `useDeleteReminder`, `ReminderForm` (Task 3).
- Produces: route `/farms/$farmId/reminders/$reminderId` (detail) + komponen `ReminderDetail({ reminderId }: { reminderId: number })`. Halaman farm `/farms/$farmId/reminders` (index) tetap menampilkan semua reminder via `ReminderList` yang card-nya clickable.

- [ ] **Step 1: Pindahkan route farm reminders ke index**

Run (di `hydroponics-web`):

```bash
mkdir -p src/routes/_authenticated/farms/\$farmId/reminders
git mv src/routes/_authenticated/farms/\$farmId/reminders.tsx src/routes/_authenticated/farms/\$farmId/reminders/index.tsx
```

Isi `index.tsx` tidak berubah (path route tetap `/_authenticated/farms/$farmId/reminders`).

- [ ] **Step 2: Buat route detail**

Buat `src/routes/_authenticated/farms/$farmId/reminders/$reminderId.tsx`:

```tsx
import { createFileRoute } from '@tanstack/react-router'
import { ReminderDetail } from '../../../../../features/reminders/components/ReminderDetail'

export const Route = createFileRoute('/_authenticated/farms/$farmId/reminders/$reminderId')({
  component: ReminderDetailPage,
})

function ReminderDetailPage() {
  const { reminderId } = Route.useParams()
  const id = Number(reminderId)

  if (!reminderId || Number.isNaN(id)) {
    return <p className="text-sm text-red-600">ID reminder tidak valid.</p>
  }

  return (
    <div className="space-y-6">
      <ReminderDetail reminderId={id} />
    </div>
  )
}
```

- [ ] **Step 3: Buat komponen `ReminderDetail`**

Buat `src/features/reminders/components/ReminderDetail.tsx`:

```tsx
import { useState } from 'react'
import { Link, useNavigate } from '@tanstack/react-router'
import type { ReminderOccurrence } from '../types'
import {
  useDeleteReminder,
  useOccurrenceDone,
  useOccurrenceSkip,
  useReminder,
} from '../hooks/useReminders'
import { ReminderForm } from './ReminderForm'
import { Button } from '../../../components/ui/button'
import { Card, CardContent } from '../../../components/ui/card'

function formatDate(value: string): string {
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const STATUS_LABEL: Record<string, string> = {
  pending: 'Menunggu',
  done: 'Selesai',
  skipped: 'Dilewati',
}

export function ReminderDetail({ reminderId }: { reminderId: number }) {
  const navigate = useNavigate()
  const { data: reminder, isLoading, isError } = useReminder(reminderId)
  const occurrenceDone = useOccurrenceDone()
  const occurrenceSkip = useOccurrenceSkip()
  const deleteReminder = useDeleteReminder()
  const [editOpen, setEditOpen] = useState(false)

  if (isLoading) return <p className="text-sm text-muted-foreground">Memuat pengingat...</p>
  if (isError || !reminder) return <p className="text-sm text-red-600">Gagal memuat pengingat.</p>

  const recurring = reminder.recurrence && reminder.recurrence.type !== 'none'
  const occurrences = reminder.occurrences ?? []

  const handleDelete = () => {
    if (!window.confirm(`Hapus reminder "${reminder.title}"?`)) return
    deleteReminder.mutate(reminder.id, {
      onSuccess: () =>
        navigate({ to: '/farms/$farmId/reminders', params: { farmId: String(reminder.farm_id) } }),
    })
  }

  return (
    <div className="space-y-4">
      <Link
        to="/farms/$farmId/reminders"
        params={{ farmId: String(reminder.farm_id) }}
        className="text-sm text-secondary hover:text-foreground hover:underline"
      >
        &larr; Kembali ke pengingat
      </Link>

      <Card>
        <CardContent className="space-y-3">
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="text-xl font-semibold text-foreground">{reminder.title}</h1>
            {recurring && (
              <span className="rounded-full bg-info/20 px-3 py-1 text-xs font-semibold tracking-[0.24em] text-info uppercase">
                Berulang
              </span>
            )}
          </div>
          {reminder.farm && <p className="text-sm text-muted-foreground">Farm: {reminder.farm.name}</p>}
          {reminder.body && <p className="text-sm text-muted-foreground">{reminder.body}</p>}
          <p className="text-xs text-muted-foreground">Mulai: {formatDate(reminder.starts_at)}</p>

          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={() => setEditOpen(true)}>
              Edit
            </Button>
            <Button
              variant="destructive"
              size="sm"
              onClick={handleDelete}
              disabled={deleteReminder.isPending}
            >
              Hapus
            </Button>
          </div>
        </CardContent>
      </Card>

      <div className="space-y-2">
        <h2 className="text-sm font-semibold text-foreground">Kemunculan</h2>
        {occurrences.length === 0 ? (
          <p className="text-sm text-muted-foreground">Belum ada kemunculan.</p>
        ) : (
          occurrences.map((occurrence: ReminderOccurrence) => (
            <Card key={occurrence.id}>
              <CardContent className="flex items-center justify-between gap-3">
                <div className="space-y-1">
                  <p className="text-sm font-medium text-foreground">
                    {formatDate(occurrence.scheduled_at)}
                  </p>
                  <p className="text-xs text-muted-foreground">
                    {STATUS_LABEL[occurrence.status] ?? occurrence.status}
                    {occurrence.notified_at && ' · Notifikasi terkirim'}
                  </p>
                </div>
                {occurrence.status === 'pending' && (
                  <div className="flex shrink-0 gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() =>
                        occurrenceDone.mutate({
                          reminderId: reminder.id,
                          occurrenceId: occurrence.id,
                        })
                      }
                      disabled={occurrenceDone.isPending}
                    >
                      Selesai
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() =>
                        occurrenceSkip.mutate({
                          reminderId: reminder.id,
                          occurrenceId: occurrence.id,
                        })
                      }
                      disabled={occurrenceSkip.isPending}
                    >
                      Lewati
                    </Button>
                  </div>
                )}
              </CardContent>
            </Card>
          ))
        )}
      </div>

      <ReminderForm
        open={editOpen}
        onOpenChange={setEditOpen}
        farmId={reminder.farm_id}
        reminder={reminder}
      />
    </div>
  )
}
```

- [ ] **Step 4: Buat card di `ReminderList` clickable**

Di `src/features/reminders/components/ReminderList.tsx`:
- Tambahkan import `useNavigate` dari `@tanstack/react-router`.
- Di dalam komponen, tambahkan `const navigate = useNavigate()`.
- Ubah `<Card key={reminder.id}>` menjadi card clickable dan tambahkan `e.stopPropagation()` pada tombol Edit dan Hapus:

```tsx
              <Card
                key={reminder.id}
                className="cursor-pointer transition-colors hover:bg-secondary/5"
                onClick={() =>
                  navigate({
                    to: '/farms/$farmId/reminders/$reminderId',
                    params: {
                      farmId: String(reminder.farm_id),
                      reminderId: String(reminder.id),
                    },
                  })
                }
              >
```

Ubah onClick Edit menjadi:

```tsx
                      onClick={(e) => {
                        e.stopPropagation()
                        setEditing(reminder)
                        setFormOpen(true)
                      }}
```

Ubah onClick Hapus menjadi:

```tsx
                      onClick={(e) => {
                        e.stopPropagation()
                        if (window.confirm(`Hapus reminder "${reminder.title}"?`)) {
                          deleteReminder.mutate(reminder.id)
                        }
                      }}
```

- [ ] **Step 5: Build & lint**

Run (di `hydroponics-web`): `npm run build && npm run lint`
Expected: tanpa error TypeScript maupun lint (route detail sudah ada sehingga Task 4 juga valid).

- [ ] **Step 6: Commit**

```bash
git -C /home/ali/Documents/Work/Agriculture/Hydroponics/hydroponics-web add src/routes/_authenticated/farms/\$farmId/reminders src/features/reminders/components/ReminderDetail.tsx src/features/reminders/components/ReminderList.tsx
git -C /home/ali/Documents/Work/Agriculture/Hydroponics/hydroponics-web commit -m "feat: add reminder detail page and clickable farm reminder cards"
```

---

## Self-Review

**1. Spec coverage:**
- Filter `?upcoming=1` server-side dengan aturan visibilitas (aktif, pending belum notify, first-cycle/window) → Task 1. ✓
- `reappear_days` default 2 dari env → Task 1 (config). ✓
- Halaman per-farm tetap semua reminder → Task 5 (hanya buat card clickable, tidak memfilter). ✓
- Card clickable global → Task 4. ✓
- Card clickable per-farm → Task 5. ✓
- Detail page dengan aksi Selesai/Lewati + Edit + Hapus → Task 5. ✓
- Alur tambah global via dialog pilih farm → Task 4. ✓
- Aksi done/skip otorisasi pembuat/target, 403, 404 → Task 2. ✓
- UI copy Bahasa Indonesia → semua task. ✓

**2. Placeholder scan:** Tidak ada TBD/TODO; semua step berisi kode lengkap. ✓

**3. Type consistency:**
- `ReminderOccurrence.status` bertipe `OccurrenceStatus`; `ReminderDetail` membandingkan `occurrence.status === 'pending'` — konsisten dengan enum nilai string. ✓
- `useOccurrenceDone`/`useOccurrenceSkip` menerima `{ reminderId, occurrenceId }`; dipanggil identik di Task 5. ✓
- `ReminderFarmPickerDialog` dipanggil dengan prop `open`, `onOpenChange`, `onSelect` — sesuai definisi. ✓
- Route detail `'/farms/$farmId/reminders/$reminderId'` dipakai konsisten di Task 4 dan 5. ✓
- Scope `upcoming()` dipanggil sebagai `$query->upcoming()` — sesuai definisi `scopeUpcoming(Builder $query): Builder`. ✓
- Method `occurrenceDone`/`occurrenceSkip` signature `(Request $request, Reminder $reminder, ReminderOccurrence $occurrence): JsonResponse` cocok dengan route binding. ✓