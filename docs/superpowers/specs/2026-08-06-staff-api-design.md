# Staff API — Design

**Date:** 2026-08-06
**Project:** hydroponics-api
**Status:** Approved

## Context

Phase-1 removed the entire web/views layer. The staff portal (dashboard, monitoring,
nutrient addition, pH down, reminders, reports) was web-only — session-based
`auth('staff')` guard with blade views, all unreachable after the web layer removal.

This design converts the staff portal to JSON API endpoints consumable by the React
frontend, reusing the existing `ApiResponses` trait pattern. Owner-side staff
management (`FarmStaffController`) is already API-ready and untouched.

## 1. Authentication

Staff authenticate with Sanctum personal tokens, mirroring the user API.

- Add `Laravel\Sanctum\HasApiTokens` to the `Staff` model
  (`app/Models/Farm/Staff.php`).
- New API controller `StaffAuthController`:
  - `POST /api/v1/staff/login` (public, throttled) — body: `farm_name`,
    `username`, `password` (reuse `StaffLoginRequest`). Resolves farm by `name`.
    Rejects inactive accounts with 403. Response:
    `{ "success": true, "data": { "token": "<personal-token>", "staff": {...} } }`.
    Token issued with `abilities(['staff'])`.
  - `POST /api/v1/staff/logout` (authed) — revokes current token.
- Sanctum resolves `auth:sanctum` to `Staff` automatically via
  `tokenable_type`/`tokenable_id` on the personal access token. No guard config
  change required.

## 2. Route group & staff middleware

New `EnsureStaff` middleware (`app/Http/Middleware/EnsureStaff.php`):
- `$request->user() instanceof Staff` and `is_active === true`, else 403 JSON.
- Registered as `staff` alias in `bootstrap/app.php`.

Route group in `routes/api.php`:

```php
Route::prefix('v1')
    ->middleware(['auth:sanctum', 'staff'])
    ->group(function () { /* staff endpoints */ });
```

All staff controllers use `$request->user()` (the token user) instead of
`auth('staff')` session lookup.

## 3. Endpoints (all JSON via `ApiResponses`)

| Endpoint | Controller | Notes |
|---|---|---|
| `POST /staff/login` | `StaffAuthController@login` | public, `throttle:login` |
| `POST /staff/logout` | `StaffAuthController@logout` | authed |
| `GET /staff/dashboard` | `StaffDashboardController@index` | farm + tank stats |
| `GET /staff/monitoring` | `StaffMonitoringController@index` | paginated, `staff_id` scoped |
| `POST /staff/monitoring` | `StaffMonitoringController@store` | sets `staff_id`, `user_id=null` |
| `GET /staff/monitoring/{dailyMonitoring}` | `StaffMonitoringController@show` | ownership: `staff_id` |
| `PATCH /staff/monitoring/{dailyMonitoring}` | `StaffMonitoringController@update` | ownership check |
| `DELETE /staff/monitoring/{dailyMonitoring}` | `StaffMonitoringController@destroy` | ownership check |
| `GET /staff/nutrients` | `StaffNutrientAdditionController@index` | |
| `POST /staff/nutrients` | `StaffNutrientAdditionController@store` | |
| `GET /staff/nutrients/{nutrientAddition}` | `StaffNutrientAdditionController@show` | |
| `PATCH /staff/nutrients/{nutrientAddition}` | `StaffNutrientAdditionController@update` | |
| `DELETE /staff/nutrients/{nutrientAddition}` | `StaffNutrientAdditionController@destroy` | |
| `GET /staff/ph-down` | `StaffPhDownController@index` | |
| `POST /staff/ph-down` | `StaffPhDownController@store` | |
| `GET /staff/ph-down/{phDownLog}` | `StaffPhDownController@show` | |
| `PATCH /staff/ph-down/{phDownLog}` | `StaffPhDownController@update` | |
| `DELETE /staff/ph-down/{phDownLog}` | `StaffPhDownController@destroy` | |
| `GET /staff/reminders` | `StaffReminderController@index` | visible via resolver |
| `POST /staff/reminders` | `StaffReminderController@store` | target resolved to farm staff |
| `DELETE /staff/reminders/{reminder}` | `StaffReminderController@destroy` | creator must be staff |
| `GET /staff/reminders/calendar` | `StaffReminderController@calendar` | `?month=YYYY-MM` → byDate JSON |
| `POST /staff/reminders/occurrences/{occurrence}/done` | `StaffReminderController@occurrenceDone` | |
| `POST /staff/reminders/occurrences/{occurrence}/skip` | `StaffReminderController@occurrenceSkip` | |
| `GET /staff/reports/monitoring` | `StaffReportController@monitoring` | `?tank_id&start_date&end_date` aggregates |
| `GET /staff/reports/nutrients` | `StaffReportController@nutrient` | |
| `GET /staff/reports/ph-down` | `StaffReportController@phDown` | |

## 4. Data flow & ownership

- `staff_id` filled automatically from the token staff; `user_id` stays null
  (consistent with current web behavior).
- Tank must belong to the staff's farm (`farm_id === staff->farm_id`) before
  create/update → else 403.
- Duplicate `log_date` per tank → 422 JSON (mirrors user API).
- Update/destroy: `abort_unless($record->staff_id === $staff->id, 403)`.
- `StaffReminderController` keeps using `ReminderTargetResolver` +
  `ReminderRecurrenceService` (already accepts `User|Staff` actors).
- Reminder `store` limited to targets within the same farm (staff-only targets),
  consistent with the web version.

## 5. Error handling

- All responses via `successResponse` / `errorResponse` / `paginatedResponse`.
- Validation failures → standard Laravel JSON 422 (`errors` object).
- 403/404 → JSON (FE must send `Accept: application/json`, same as user API).

## 6. Tests

New feature tests under `tests/Feature/Staff/`:

- `StaffAuthApiTest.php`: login success / wrong password / wrong farm / inactive /
  logout.
- `StaffMonitoringApiTest.php`: store with `staff_id` set, cross-farm tank 403,
  duplicate date 422, update/destroy ownership 403.
- `StaffNutrientApiTest.php` & `StaffPhDownApiTest.php`: CRUD + 403 cases.
- `StaffReminderApiTest.php`: store with staff target, index visible subset,
  destroy only own-created, occurrence done/skip.
- `StaffReportApiTest.php`: aggregates for monitoring/nutrients/ph-down.

All use `Sanctum::actingAs($staff)`.

## 7. Removals

- The 8 legacy web controllers are replaced by the new API controllers
  (same names in `App\Http\Controllers\Staff\*` namespace), then the web
  versions deleted. Consistent with Phase-1.
- Session `auth('staff')` guard usage in `ActivityLogObserver` is preserved
  (falls back to null for API-only traffic).

## Out of scope

- User-side portal changes (unchanged).
- Staff push-subscription endpoints (already exist at `/api/v1/push-subscriptions`,
  user-side; staff FCM not included unless requested).
- Android/Capacitor layer.
