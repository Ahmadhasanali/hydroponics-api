# No-Farm Empty State Handling — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the `/tank` 404 for users without a farm and hide farm-dependent menus, showing a friendly "Buat Farm Baru" empty state instead.

**Architecture:** Add `hasFarm()`/`selectedFarm()` helpers on the base `Controller`. Register a view composer that shares `$hasFarm` with the sidebar and bottom-nav so farm-dependent menus hide when the user has no farm. Guard every farm-dependent controller to render a shared `farm.no-farm` empty-state view instead of crashing on a `null` farm id.

**Tech Stack:** Laravel 13, PHP 8.5, Blade, PHPUnit 12, Tailwind v4, Laravel Sail.

## Global Constraints

- Run all PHP/artisan/composer/npm commands through `vendor/bin/sail` (see AGENTS.md).
- Every change must be tested. Use `vendor/bin/sail artisan test --compact --filter=<TestName>` to run a single test.
- After modifying PHP files, run `vendor/bin/sail bin pint --dirty --format agent`.
- Use `use Illuminate\Contracts\View\View;` for view return types (matches existing controllers).
- No new dependencies. No new top-level folders.
- Existing tests using `setUpFarm()` (which attaches the user to a farm and sets `selected_farm_id`) must keep passing.

---

### Task 1: Shared helpers on base Controller + unit tests

**Files:**
- Modify: `app/Http/Controllers/Controller.php`
- Test: `tests/Unit/Controllers/ControllerFarmHelpersTest.php` (create)

**Interfaces:**
- Consumes: nothing.
- Produces: `Controller::hasFarm(Request $request): bool`, `Controller::selectedFarm(Request $request): ?Farm`. Tasks 4-6 call these.

- [ ] **Step 1: Write the failing unit tests**

Create `tests/Unit/Controllers/ControllerFarmHelpersTest.php`:

```php
<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class FarmHelperTestController extends Controller
{
    public function exposeHasFarm(Request $request): bool
    {
        return $this->hasFarm($request);
    }

    public function exposeSelectedFarm(Request $request): ?Farm
    {
        return $this->selectedFarm($request);
    }
}

class ControllerFarmHelpersTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeRequest(User $user): Request
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    public function test_has_farm_returns_false_when_user_has_no_farms(): void
    {
        $user = User::factory()->create();
        $controller = new FarmHelperTestController;

        $this->assertFalse($controller->exposeHasFarm($this->makeRequest($user)));
    }

    public function test_has_farm_returns_true_when_user_has_farm(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);

        $this->assertTrue((new FarmHelperTestController)->exposeHasFarm($this->makeRequest($user)));
    }

    public function test_selected_farm_returns_null_when_no_farms(): void
    {
        $user = User::factory()->create();

        $this->assertNull((new FarmHelperTestController)->exposeSelectedFarm($this->makeRequest($user)));
    }

    public function test_selected_farm_uses_session_farm(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        app('session.store')->put('selected_farm_id', $farm->id);

        $this->assertSame($farm->id, (new FarmHelperTestController)->exposeSelectedFarm($this->makeRequest($user))->id);
    }

    public function test_selected_farm_falls_back_to_first_farm_when_session_stale(): void
    {
        $user = User::factory()->create();
        $farmA = Farm::factory()->create(['created_by' => $user->id]);
        $farmB = Farm::factory()->create(['created_by' => $user->id]);
        $farmA->users()->attach($user->id, ['role' => 'owner']);
        $farmB->users()->attach($user->id, ['role' => 'owner']);
        app('session.store')->put('selected_farm_id', 999999);

        $this->assertSame($farmA->id, (new FarmHelperTestController)->exposeSelectedFarm($this->makeRequest($user))->id);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/sail artisan test --compact tests/Unit/Controllers/ControllerFarmHelpersTest.php`
Expected: FAIL — "Call to undefined method App\Http\Controllers\Controller::hasFarm()".

- [ ] **Step 3: Implement the helpers**

Replace the contents of `app/Http/Controllers/Controller.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    protected function hasFarm(Request $request): bool
    {
        return $request->user()->farms()->exists();
    }

    protected function selectedFarm(Request $request): ?Farm
    {
        $farmId = $request->session()->get('selected_farm_id');

        if ($farmId) {
            $farm = $request->user()->farms()->find($farmId);

            if ($farm) {
                return $farm;
            }
        }

        return $request->user()->farms()->first();
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/sail artisan test --compact tests/Unit/Controllers/ControllerFarmHelpersTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Run pint**

Run: `vendor/bin/sail bin pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Controller.php tests/Unit/Controllers/ControllerFarmHelpersTest.php
git commit -m "feat: helper hasFarm & selectedFarm di base Controller"
```

---

### Task 2: Reusable empty-state views + dashboard refactor

**Files:**
- Create: `resources/views/partials/no-farm-card.blade.php`
- Create: `resources/views/farm/no-farm.blade.php`
- Modify: `resources/views/dashboard/index.blade.php:14-29`
- Test: `tests/Feature/Farm/NoFarmEmptyStateTest.php` (create)

**Interfaces:**
- Consumes: nothing (views only).
- Produces: `partials.no-farm-card` (a full-width empty-state card with a `farm.create` CTA), `farm.no-farm` (full page extending `layouts.app`). Tasks 3-6 render/include these.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/Farm/NoFarmEmptyStateTest.php`:

```php
<?php

namespace Tests\Feature\Farm;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NoFarmEmptyStateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_no_farm_page_renders_empty_state(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('farm.no-farm'));

        $response->assertOk();
        $response->assertSee('Belum Ada Farm');
        $response->assertSee('Buat Farm Baru');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Farm/NoFarmEmptyStateTest.php`
Expected: FAIL — "Route [farm.no-farm] not defined" (view exists but has no route yet; we add a route to make it testable).

- [ ] **Step 3: Create the shared card partial**

Create `resources/views/partials/no-farm-card.blade.php` (copy of the dashboard empty-state markup, lines 15-29 of `dashboard/index.blade.php`):

```blade
<div class="flex flex-col items-center justify-center rounded-[2rem] border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center">
    <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-[#ffce54]/20 text-2xl text-[#d4a020]">
        <i class="bi bi-buildings"></i>
    </div>
    <h2 class="mt-6 text-xl font-semibold text-slate-900">Belum Ada Farm</h2>
    <p class="mt-2 max-w-md text-sm text-slate-500">Anda belum terdaftar di farm manapun. Buat farm baru
        untuk memulai monitoring hidroponik.</p>
    <a href="{{ route('farm.create') }}"
        class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-6 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
        <i class="bi bi-plus-lg"></i>
        Buat Farm Baru
    </a>
</div>
```

- [ ] **Step 4: Create the `farm.no-farm` page**

Create `resources/views/farm/no-farm.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Belum Ada Farm')

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                @include('partials.no-farm-card')
            </section>

            @include('partials.footer')
        </main>
    </div>
@endsection
```

- [ ] **Step 5: Refactor dashboard to use the shared card**

In `resources/views/dashboard/index.blade.php`, replace the whole `@if (!$selectedFarm) ... @elseif($tanks->isEmpty())` empty-state block (currently lines 13-29) with:

```blade
@if (!$selectedFarm)
    @include('partials.no-farm-card')
@elseif($tanks->isEmpty())
```

Keep the rest of the `@elseif($tanks->isEmpty())` branch and the closing `@endif` untouched.

- [ ] **Step 6: Add a test route for the page**

In `routes/web.php`, after the `require` statements, add:

```php
Route::get('/farm/no-farm', function () {
    return view('farm.no-farm');
})->middleware(['auth', 'verified'])->name('farm.no-farm');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Farm/NoFarmEmptyStateTest.php`
Expected: PASS.

- [ ] **Step 8: Run pint**

Run: `vendor/bin/sail bin pint --dirty --format agent`

- [ ] **Step 9: Commit**

```bash
git add resources/views/partials/no-farm-card.blade.php resources/views/farm/no-farm.blade.php resources/views/dashboard/index.blade.php routes/web.php tests/Feature/Farm/NoFarmEmptyStateTest.php
git commit -m "feat: view empty-state farm.no-farm reusable & refactor dashboard"
```

---

### Task 3: View composer hides farm menus + sidebar/bottom-nav CTA

**Files:**
- Modify: `app/Providers/AppServiceProvider.php:45-68`
- Modify: `resources/views/partials/sidebar.blade.php`
- Modify: `resources/views/partials/bottom-nav.blade.php`
- Test: `tests/Feature/Frontend/NoFarmMenuVisibilityTest.php` (create)

**Interfaces:**
- Consumes: nothing.
- Produces: `$hasFarm` boolean available in `partials.sidebar` and `partials.bottom-nav` on every page. Tasks 4-6 rely on the sidebar/bottom-nav being correct for both farm/no-farm users.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/Frontend/NoFarmMenuVisibilityTest.php`:

```php
<?php

namespace Tests\Feature\Frontend;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NoFarmMenuVisibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sidebar_hides_farm_menus_for_user_without_farm(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('href="' . route('tank.index') . '"');
        $response->assertSee('Buat Farm Baru');
    }

    public function test_sidebar_shows_farm_menus_for_user_with_farm(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('href="' . route('tank.index') . '"');
        $response->assertDontSee('Buat Farm Baru');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Frontend/NoFarmMenuVisibilityTest.php`
Expected: FAIL — no-farm user still sees the Tank menu (`href="/tank"` present).

- [ ] **Step 3: Register the view composer**

In `app/Providers/AppServiceProvider.php`:
- Add `use Illuminate\Support\Facades\View;` to the imports.
- In `boot()`, after the observers, add:

```php
View::composer(['partials.sidebar', 'partials.bottom-nav'], function ($view) {
    $view->with('hasFarm', auth()->check() && auth()->user()->farms()->exists());
});
```

- [ ] **Step 4: Update the sidebar**

In `resources/views/partials/sidebar.blade.php`:
- Wrap everything from the `{{-- Tank --}}` link (line 37) through the `{{-- Activity Logs --}}` link (line 91) inside `@if ($hasFarm)` / `@endif`.
- Add an `@else` branch with a CTA right before the `@endif`:

```blade
@else
    <a href="{{ route('farm.create') }}"
        class="flex items-center gap-3 rounded-2xl bg-[#ffce54] px-3 py-2.5 text-sm font-semibold text-[#1a1c1e] transition hover:bg-[#f0b830]">
        <i class="bi bi-plus-lg text-base"></i>
        Buat Farm Baru
    </a>
@endif
```

Dashboard and Farm links (lines 24-35) stay outside the conditional.

- [ ] **Step 5: Update the bottom-nav**

In `resources/views/partials/bottom-nav.blade.php`:
- Wrap the Catat button `div` (the `<div class="relative flex flex-col items-center">...</div>`, lines 10-30) and the Riwayat link (lines 35-39) inside `@if ($hasFarm)` / `@endif`.
- Add an `@else` branch with a single "Buat Farm" item:

```blade
@else
    <a href="{{ route('farm.create') }}"
        class="flex flex-col items-center gap-0.5 py-2.5 text-[10px] font-semibold text-slate-500">
        <i class="bi bi-plus-circle text-xl"></i>
        Buat Farm
    </a>
@endif
```

Dashboard and Profil links stay outside the conditional.

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Frontend/NoFarmMenuVisibilityTest.php`
Expected: PASS (2 tests).

- [ ] **Step 7: Run pint**

Run: `vendor/bin/sail bin pint --dirty --format agent`

- [ ] **Step 8: Commit**

```bash
git add app/Providers/AppServiceProvider.php resources/views/partials/sidebar.blade.php resources/views/partials/bottom-nav.blade.php tests/Feature/Frontend/NoFarmMenuVisibilityTest.php
git commit -m "feat: sembunyikan menu farm-dependent saat user tanpa farm"
```

---

### Task 4: Guard TankController (fixes the 404)

**Files:**
- Modify: `app/Http/Controllers/TankController.php:14-67`
- Test: `tests/Feature/NoFarm/NoFarmTankTest.php` (create)

**Interfaces:**
- Consumes: `Controller::hasFarm()`, `Controller::selectedFarm()`, view `farm.no-farm`.
- Produces: corrected behavior — `/tank` returns 200 with empty state (not 404) for no-farm users; `tank.store` redirects to `farm.create` instead of writing orphan rows.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/NoFarm/NoFarmTankTest.php`:

```php
<?php

namespace Tests\Feature\NoFarm;

use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NoFarmTankTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_no_farm_user_gets_empty_state_on_tank_index_not_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('tank.index'));

        $response->assertOk();
        $response->assertSee('Belum Ada Farm');
        $response->assertSee('Buat Farm Baru');
    }

    public function test_no_farm_user_gets_empty_state_on_tank_create(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('tank.create'));

        $response->assertOk();
        $response->assertSee('Buat Farm Baru');
    }

    public function test_no_farm_user_cannot_store_tank(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('tank.store'), [
            'name' => 'Tank A1',
            'capacity_liter' => 100,
        ]);

        $response->assertRedirect(route('farm.create'));
        $this->assertDatabaseCount('tanks', 0);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/sail artisan test --compact tests/Feature/NoFarm/NoFarmTankTest.php`
Expected: FAIL — first test currently returns 404 (the reported bug); store test currently inserts an orphan tank.

- [ ] **Step 3: Guard `index`**

In `app/Http/Controllers/TankController.php`, replace `index()` (lines 14-25) with:

```php
public function index(Request $request): View
{
    if (! $this->hasFarm($request)) {
        return view('farm.no-farm');
    }

    $farm = $this->selectedFarm($request);

    return view('tank.index', [
        'farm' => $farm,
        'tanks' => $farm->tanks()->orderBy('id')->get(),
    ]);
}
```

- [ ] **Step 4: Guard `create`**

Replace `create()` (lines 27-34) with:

```php
public function create(Request $request): View
{
    if (! $this->hasFarm($request)) {
        return view('farm.no-farm');
    }

    return view('tank.create', [
        'farmId' => $this->selectedFarm($request)->id,
    ]);
}
```

- [ ] **Step 5: Guard `store`**

Replace the start of `store()` (lines 49-50) — replace `$farmId = $request->session()->get('selected_farm_id');` with:

```php
if (! $this->hasFarm($request)) {
    return redirect()->route('farm.create');
}

$farmId = $this->selectedFarm($request)->id;
```

The rest of `store()` stays unchanged.

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/NoFarm/NoFarmTankTest.php`
Expected: PASS (3 tests).

- [ ] **Step 7: Confirm existing Tank tests still pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Tank/TankTest.php`
Expected: PASS.

- [ ] **Step 8: Run pint**

Run: `vendor/bin/sail bin pint --dirty --format agent`

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/TankController.php tests/Feature/NoFarm/NoFarmTankTest.php
git commit -m "fix: /tank 404 saat user tanpa farm - tampilkan empty state"
```

---

### Task 5: Guard monitoring controllers (DailyMonitoring, NutrientAddition, PhDownLog)

**Files:**
- Modify: `app/Http/Controllers/DailyMonitoringController.php`
- Modify: `app/Http/Controllers/NutrientAdditionController.php`
- Modify: `app/Http/Controllers/PhDownLogController.php`
- Test: `tests/Feature/NoFarm/NoFarmMonitoringTest.php` (create)

**Interfaces:**
- Consumes: `Controller::hasFarm()`, `Controller::selectedFarm()`, view `farm.no-farm`.
- Produces: consistent empty state for all monitoring index/create pages; store routes redirect to `farm.create` for no-farm users.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/NoFarm/NoFarmMonitoringTest.php`:

```php
<?php

namespace Tests\Feature\NoFarm;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NoFarmMonitoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_no_farm_user_gets_empty_state_on_all_monitoring_pages(): void
    {
        $user = User::factory()->create();

        $routes = [
            'daily-monitoring.index',
            'daily-monitoring.create',
            'nutrient-addition.index',
            'nutrient-addition.create',
            'ph-down-log.index',
            'ph-down-log.create',
            'activity-logs.index',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($user)->get(route($route));

            $response->assertOk();
            $response->assertSee('Buat Farm Baru');
        }
    }

    public function test_no_farm_user_cannot_store_monitoring_data(): void
    {
        $user = User::factory()->create();

        $post = $this->actingAs($user)->post(route('daily-monitoring.store'), [
            'tank_id' => 1,
            'log_date' => now()->toDateString(),
            'ppm' => 850,
            'ph' => 6.2,
        ]);
        $post->assertRedirect(route('farm.create'));

        $nutrient = $this->actingAs($user)->post(route('nutrient-addition.store'), [
            'tank_id' => 1,
            'log_date' => now()->toDateString(),
            'ppm_before' => 800,
            'ppm_after' => 900,
            'nutrient_a_ml' => 10,
            'nutrient_b_ml' => 10,
        ]);
        $nutrient->assertRedirect(route('farm.create'));

        $ph = $this->actingAs($user)->post(route('ph-down-log.store'), [
            'tank_id' => 1,
            'log_date' => now()->toDateString(),
            'ph_before' => 7.0,
            'ph_after' => 6.5,
            'ph_down_ml' => 5,
        ]);
        $ph->assertRedirect(route('farm.create'));

        $this->assertDatabaseCount('daily_monitorings', 0);
        $this->assertDatabaseCount('nutrient_additions', 0);
        $this->assertDatabaseCount('ph_down_logs', 0);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/sail artisan test --compact tests/Feature/NoFarm/NoFarmMonitoringTest.php`
Expected: FAIL — pages render empty (no "Buat Farm Baru"), store routes create orphan rows.

- [ ] **Step 3: Guard `DailyMonitoringController`**

In `app/Http/Controllers/DailyMonitoringController.php`:
- In `index()` (line 15), replace `$farmId = $request->session()->get('selected_farm_id');` with:

```php
if (! $this->hasFarm($request)) {
    return view('farm.no-farm');
}

$farmId = $this->selectedFarm($request)->id;
```

- In `create()` (line 32), apply the same replacement.
- In `store()`, add at the top of the method body (before the `validate` call):

```php
if (! $this->hasFarm($request)) {
    return redirect()->route('farm.create');
}
```

- [ ] **Step 4: Guard `NutrientAdditionController`**

In `app/Http/Controllers/NutrientAdditionController.php`:
- In `index()` (line 15) and `create()` (line 27), apply the same guard + `$farmId = $this->selectedFarm($request)->id;` replacement.
- In `store()`, add at the top of the method body:

```php
if (! $this->hasFarm($request)) {
    return redirect()->route('farm.create');
}
```

- [ ] **Step 5: Guard `PhDownLogController`**

In `app/Http/Controllers/PhDownLogController.php`:
- In `index()` (line 15) and `create()` (line 27), apply the same guard + `$farmId = $this->selectedFarm($request)->id;` replacement.
- In `store()`, add at the top of the method body:

```php
if (! $this->hasFarm($request)) {
    return redirect()->route('farm.create');
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/NoFarm/NoFarmMonitoringTest.php`
Expected: PASS (2 tests).

- [ ] **Step 7: Confirm existing monitoring tests still pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/DailyMonitoring/DailyMonitoringTest.php`
Run: `vendor/bin/sail artisan test --compact tests/Feature/NutrientAddition/NutrientAdditionTest.php`
Run: `vendor/bin/sail artisan test --compact tests/Feature/PhDownLog/PhDownLogTest.php`
Run: `vendor/bin/sail artisan test --compact tests/Feature/ActivityLog/ActivityLogTest.php`
Expected: PASS.

- [ ] **Step 8: Run pint**

Run: `vendor/bin/sail bin pint --dirty --format agent`

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/DailyMonitoringController.php app/Http/Controllers/NutrientAdditionController.php app/Http/Controllers/PhDownLogController.php tests/Feature/NoFarm/NoFarmMonitoringTest.php
git commit -m "fix: guard monitoring pages saat user tanpa farm"
```

---

### Task 6: Guard ReportController

**Files:**
- Modify: `app/Http/Controllers/ReportController.php`
- Test: `tests/Feature/NoFarm/NoFarmReportTest.php` (create)

**Interfaces:**
- Consumes: `Controller::hasFarm()`, `Controller::selectedFarm()`, view `farm.no-farm`.
- Produces: consistent empty state for the three report pages.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/NoFarm/NoFarmReportTest.php`:

```php
<?php

namespace Tests\Feature\NoFarm;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NoFarmReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_no_farm_user_gets_empty_state_on_all_report_pages(): void
    {
        $user = User::factory()->create();

        $routes = [
            'reports.monitoring',
            'reports.nutrient',
            'reports.ph-down',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($user)->get(route($route));

            $response->assertOk();
            $response->assertSee('Buat Farm Baru');
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/sail artisan test --compact tests/Feature/NoFarm/NoFarmReportTest.php`
Expected: FAIL — pages render empty (no "Buat Farm Baru").

- [ ] **Step 3: Guard all three report methods**

In `app/Http/Controllers/ReportController.php`, in each of `monitoring()` (line 16), `nutrient()` (line 44), and `phDown()` (line 68), replace `$farmId = $request->session()->get('selected_farm_id');` with:

```php
if (! $this->hasFarm($request)) {
    return view('farm.no-farm');
}

$farmId = $this->selectedFarm($request)->id;
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/NoFarm/NoFarmReportTest.php`
Expected: PASS.

- [ ] **Step 5: Confirm existing report tests still pass**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Report/ReportTest.php`
Expected: PASS.

- [ ] **Step 6: Run pint**

Run: `vendor/bin/sail bin pint --dirty --format agent`

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ReportController.php tests/Feature/NoFarm/NoFarmReportTest.php
git commit -m "fix: guard halaman laporan saat user tanpa farm"
```

---

### Task 7: Final verification

**Files:**
- None (verification only).

- [ ] **Step 1: Run the full test suite**

Run: `vendor/bin/sail artisan test --compact`
Expected: all tests pass, including existing Tank/Monitoring/Report/Farm/Frontend tests.

- [ ] **Step 2: Run pint**

Run: `vendor/bin/sail bin pint --dirty --format agent`
Expected: no remaining dirty files (or auto-fixes applied).

- [ ] **Step 3: Manual smoke check (optional)**

With the running dev server (`vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`), log in as `ahmadhasanali68@gmail.com` / `password` (a user with no farm):
- Visit `/tank` → expect the "Belum Ada Farm" empty state, not a 404.
- Sidebar shows no Tank/Monitoring/Reports/Activity Logs menus; shows "Buat Farm Baru".
- Visit `/farm/create` → works normally.

- [ ] **Step 4: Commit any pint fixes if applied**

```bash
git status
git add -A
git commit -m "style: pint formatting"
```
