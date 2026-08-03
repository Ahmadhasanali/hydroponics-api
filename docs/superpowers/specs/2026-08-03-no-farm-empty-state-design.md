# Design: No-Farm Empty State Handling

Date: 2026-08-03
Status: Approved

## Problem

A user who is not yet a member of any farm gets a **404 Not Found** page when
visiting `/tank` (and other farm-dependent pages).

### Root Cause

`TankController@index` reads `selected_farm_id` from the session and calls:

```php
$farm = Farm::with('tanks')->findOrFail($farmId);
```

For a user with no farm, `selected_farm_id` is never set (only `DashboardController`
and `FarmController@store` set it), so `$farmId` is `null`. `findOrFail(null)` throws
`ModelNotFoundException`, which Laravel renders as a 404.

Other farm-dependent controllers (`DailyMonitoringController`, `NutrientAdditionController`,
`PhDownLogController`, `ActivityLogController`, `ReportController`) read the same
`selected_farm_id` but use `where('farm_id', $farmId)` instead of `findOrFail()`, so
they do not 404 — they silently render empty pages.

Additionally, the sidebar and bottom-nav always show farm-dependent menu items
(Tank, Daily Monitoring, AB Mix, pH Down, Reports, Activity Logs) regardless of
whether the user has a farm.

## Goals

1. Fix the 404: farm-dependent pages render a friendly empty state instead of a 404
   when the user has no farm.
2. Hide farm-dependent menus when the user has no farm, showing a "Buat Farm Baru"
   CTA instead.
3. Centralize the "current farm" resolution so future farm-dependent pages get the
   same behavior for free.

## Non-Goals

- No changes to the existing farm-switcher behavior on the dashboard.
- No changes to authorization/policy for existing farm members.
- No new farm-related features.

## Design

### 1. Shared helper on base `Controller`

Add two protected methods to `app/Http/Controllers/Controller.php`:

- `hasFarm(Request $request): bool` — returns `$request->user()->farms()->exists()`.
- `selectedFarm(Request $request): ?Farm` — looks up the session `selected_farm_id`
  scoped to the user's own farms (`$request->user()->farms()->find($id)`); if the
  session value is missing or stale (not one of the user's farms), falls back to the
  user's first farm. Returns `null` only when the user has no farms. Mirrors the
  fallback logic in `DashboardController@index` line 28.

### 2. Reusable empty-state view

- Create `resources/views/farm/no-farm.blade.php` — a full page extending
  `layouts.app`, including `partials.sidebar` and `partials.topbar`, showing the
  "Belum Ada Farm" card and a "Buat Farm Baru" CTA (reuses the existing empty-state
  markup from `dashboard/index.blade.php`).
- Extract the empty card markup into `resources/views/partials/no-farm-card.blade.php`
  so both `dashboard/index.blade.php` and `farm/no-farm.blade.php` share it (DRY).

### 3. View Composer to hide farm-dependent menus

Register in `AppServiceProvider@boot`:

```php
View::composer(['partials.sidebar', 'partials.bottom-nav'], function ($view) {
    $view->with('hasFarm', auth()->check() && auth()->user()->farms()->exists());
});
```

- `partials/sidebar.blade.php`: wrap the Tank, Monitoring, Reports, and Activity Logs
  nav items in `@if ($hasFarm)`. When `!$hasFarm`, render a "Buat Farm Baru" CTA block.
- `partials/bottom-nav.blade.php`: when `!$hasFarm`, replace the "Catat" button (and its
  submenu) and the "Riwayat" item with a single "Buat Farm" item linking to
  `farm.create`; keep Dashboard and Profil. Grid stays 4 columns.

### 4. Guard farm-dependent controllers

Each guarded method starts with the same pattern, then resolves the farm id once via
the shared helper (so the helper is used, not dead code):

```php
if (! $this->hasFarm($request)) {
    return view('farm.no-farm');
}
$farmId = $this->selectedFarm($request)->id;
```

For **GET index/create** methods, return `view('farm.no-farm')` when the user has no
farm (instead of proceeding with `null` farm id):

- `TankController@index`, `TankController@create` — fixes the reported 404.
- `DailyMonitoringController@index`, `DailyMonitoringController@create`.
- `NutrientAdditionController@index`, `NutrientAdditionController@create`.
- `PhDownLogController@index`, `PhDownLogController@create`.
- `ActivityLogController@index`.
- `ReportController@monitoring`, `ReportController@nutrient`, `ReportController@phDown`.

For **POST store** methods, redirect to `farm.create` when the user has no farm
(prevents writing orphan records with `farm_id = null`):

- `TankController@store`.
- `DailyMonitoringController@store`.
- `NutrientAdditionController@store`.
- `PhDownLogController@store`.

### 5. Tests

Add feature tests covering the no-farm user:

- No-farm user GET `/tank` → 200, sees "Buat Farm Baru", not a 404.
- No-farm user GET each guarded page (daily-monitoring, nutrient-addition,
  ph-down-log, activity-logs, reports.*) → 200 with the empty state.
- No-farm user POST store routes → redirect to `farm.create`, no orphan rows.

Existing tests (which set up a farm via `setUpFarm()`) must continue to pass.

## Affected Files

- `app/Http/Controllers/Controller.php` — new helper methods.
- `app/Http/Controllers/TankController.php`
- `app/Http/Controllers/DailyMonitoringController.php`
- `app/Http/Controllers/NutrientAdditionController.php`
- `app/Http/Controllers/PhDownLogController.php`
- `app/Http/Controllers/ActivityLogController.php`
- `app/Http/Controllers/ReportController.php`
- `app/Providers/AppServiceProvider.php` — view composer.
- `resources/views/partials/sidebar.blade.php`
- `resources/views/partials/bottom-nav.blade.php`
- `resources/views/partials/no-farm-card.blade.php` (new)
- `resources/views/farm/no-farm.blade.php` (new)
- `resources/views/dashboard/index.blade.php` — use shared empty card.
- `tests/Feature/...` — new no-farm tests.
