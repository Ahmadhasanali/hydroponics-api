# Design: Fitur Reminder

Tanggal: 2026-08-03

## Ringkasan

Fitur reminder untuk kebun hidroponik. User (owner/manager) dan staff dapat membuat pengingat yang dikirim sebagai push notification pada waktu terjadwal. Reminder dapat bersifat sekali atau berulang (interval hari, mingguan, bulanan), dapat memiliki advance reminder (pengingat sebelum jadwal), mendukung tracking "sudah dikerjakan" per occurrence, dan ditampilkan dalam tampilan kalender.

Reminder hanya terlihat oleh pembuat dan target-nya. Pembuat hanya dapat menarget role yang setara atau di bawahnya.

## Terminologi

- **Reminder**: pengingat inti (title, body, jadwal awal, konfigurasi perulangan).
- **Occurrence**: satu instance konkret dari reminder pada tanggal-jam tertentu (hasil penjabaran jadwal).
- **Target**: penerima reminder (User atau Staff).
- **Advance reminder**: notifikasi pengingat yang dikirim sebelum waktu occurrence sebenarnya.

## Asumsi & Keputusan

- Hierarki role: **owner (2) > manager (1) > staff (0)**.
- Staff menggunakan guard `staff`, terikat pada satu farm, dan menjadi bagian dari target "semua member".
- Push notification (FCM) adalah satu-satunya kanal notifikasi.
- Hanya pembuat reminder yang dapat mengedit/menghapus reminder-nya. Owner farm **tidak** dapat edit/hapus reminder milik member lain.
- Target dijabarkan menjadi daftar konkret saat reminder dibuat (bukan disimpan sebagai scope).
- Google Calendar sync akan dipertimbangkan di masa depan — struktur data dijaga agar mendukungnya (occurrence punya `scheduled_at` konkret), namun tidak dibangun sekarang.

## Skema Database

### Tabel `reminders`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `farm_id` | FK `farms.id` | Kebun tempat reminder dibuat |
| `created_by_type` | string | `App\Models\User` atau `App\Models\Farm\Staff` |
| `created_by_id` | bigint | ID pembuat |
| `title` | string | Judul reminder |
| `body` | text | Isi/pesan reminder |
| `starts_at` | datetime | Waktu occurrence pertama |
| `recurrence` | json, nullable | Konfigurasi perulangan (lihat bawah) |
| `advance_notify_minutes` | int, nullable | Menit sebelum jadwal untuk advance reminder |
| `is_active` | boolean | Default `true` |
| `created_at` / `updated_at` | timestamps | |
| `deleted_at` | soft delete, nullable | |

Index: `farm_id`, `(created_by_type, created_by_id)`.

`recurrence` JSON, salah satu bentuk:

```json
{ "type": "none" }
{ "type": "interval", "every_days": 3 }
{ "type": "weekly", "days_of_week": ["mon", "wed"] }
{ "type": "monthly", "days_of_month": [1, 15] }
```

### Tabel `reminder_targets`

Daftar penerima konkret, dijabarkan saat pembuatan.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `reminder_id` | FK `reminders.id` | cascade on delete |
| `targetable_type` | string | `App\Models\User` atau `App\Models\Farm\Staff` |
| `targetable_id` | bigint | ID target |
| `created_at` / `updated_at` | timestamps | |

Index: `(reminder_id)`, `(targetable_type, targetable_id)`.

### Tabel `reminder_occurrences`

Setiap instance jadwal yang harus dikirim dan/atau ditrack.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `reminder_id` | FK `reminders.id` | cascade on delete |
| `scheduled_at` | datetime | Waktu occurrence |
| `advance_notify_at` | datetime, nullable | Waktu kirim advance notif |
| `advance_notified_at` | datetime, nullable | Waktu advance notif terkirim |
| `notified_at` | datetime, nullable | Waktu notif utama terkirim |
| `status` | string enum | `pending` \| `done` \| `skipped` |
| `completed_by_type` | string, nullable | siapa menandai done |
| `completed_by_id` | bigint, nullable | |
| `completed_at` | datetime, nullable | |
| `created_at` / `updated_at` | timestamps | |

Unique index: `(reminder_id, scheduled_at)`.

### Tabel `push_subscriptions` (diubah)

Dari `user_id` tunggal menjadi polymorphic agar User dan Staff sama-sama dapat menerima push.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `subscribable_type` | string | `App\Models\User` atau `App\Models\Farm\Staff` |
| `subscribable_id` | bigint | |
| `fcm_token` | string, unique | |
| `platform` | string | default `android` |
| `device_info` | string, nullable | |
| `created_at` / `updated_at` | timestamps | |

Migrasi berisi data migration: pindahkan `user_id` lama ke `subscribable_type = App\Models\User` + `subscribable_id = user_id`.

## Model & Relasi

- `Reminder`:
  - `farm(): BelongsTo`
  - `creator(): MorphTo` (alias ke `created_by`)
  - `targets(): HasMany`
  - `occurrences(): HasMany`
- `ReminderTarget`:
  - `reminder(): BelongsTo`
  - `targetable(): MorphTo`
- `ReminderOccurrence`:
  - `reminder(): BelongsTo`
  - `completer(): MorphTo` (alias ke `completed_by`)
- `User`: tambah `morphMany` `pushSubscriptions`, `remindersCreated`, `reminderTargets`.
- `Staff`: tambah `morphMany` `pushSubscriptions`, `remindersCreated`, `reminderTargets`.

## Hirarki & Pembatasan Target

Tingkat role dihitung dari konteks: **owner (2) > manager (1) > staff (0)**.

Aturan saat pembuatan reminder:

- Pembuat hanya dapat menarget role **setara atau di bawah** levelnya.
- **Owner** (level 2): dapat menarget semua member farm (owner, manager, staff).
- **Manager** (level 1): dapat menarget manager lain dan staff.
- **Staff** (level 0): hanya dapat menarget sesama staff di farm yang sama, dan dirinya sendiri.
- Target selalu dibatasi pada farm yang sama dengan pembuat.
- `created_by` polymorphic sehingga User (owner/manager) dan Staff sama-sama bisa menjadi pembuat.

Mode target yang didukung:

- `self` — hanya pembuat.
- `all` — semua member farm (User + Staff) pada saat pembuatan, **termasuk pembuat**.
- `specific` — daftar `target_ids` yang lolos validasi hierarki.

## Alur Pengiriman (Scheduler)

Command `reminders:dispatch` dijadwalkan **setiap menit** di `bootstrap/app.php`.

1. Ambil occurrence yang perlu advance notif: status `pending`, `advance_notify_at <= now`, dan `advance_notified_at IS NULL`. Kirim advance notif, lalu set `advance_notified_at`.
2. Ambil occurrence yang perlu notif utama: status `pending`, `scheduled_at <= now`, dan `notified_at IS NULL`. Kirim notif utama, lalu set `notified_at`.
3. Untuk tiap occurrence, muat reminder + targets, kirim push ke tiap target via `PushNotificationService` yang sudah ada.
4. Setelah notif utama terkirim, occurrence tetap `pending` menunggu tracking done/skipped oleh user, dan tetap tampil di kalender. Kolom `notified_at` mencegah pengiriman ulang.
5. Setelah notif utama terkirim pada reminder berulang, jabarkan occurrence berikutnya dari `recurrence` JSON dan simpan.
6. Recurrence juga dijabarkan **on-demand** saat kalender diminta untuk bulan tertentu. Batasi jumlah occurrence ke depan (mis. 100) agar tidak meledak.

Pertimbangan waktu: command berjalan tiap menit; pastikan tidak terjadi double-send dengan menandai `advance_notified_at` / memfilter occurrence yang sudah diproses dalam batch yang sama.

## Tracking Done per Occurrence

- Target (atau pembuat) dapat menandai occurrence `done`: set `status = done`, `completed_by`, `completed_at`.
- Menandai `skipped`: melewati satu occurrence tanpa mengubah recurrence.
- Kalender menampilkan semua occurrence dalam rentang tanggal; hanya pembuat dan target yang melihatnya.

## Fitur Advance Reminder

- `advance_notify_minutes` pada reminder: jumlah menit sebelum `scheduled_at` saat advance notif dikirim (mis. 30, 60, 1440 = H-1).
- Saat occurrence dibuat, hitung `advance_notify_at = scheduled_at - advance_notify_minutes`.
- Kirim advance notif terlebih dahulu, lalu notif utama saat `scheduled_at` tiba.
- Hanya satu advance notif per occurrence (dijaga oleh kolom `advance_notified_at`).

## UI & Routes

Route di bawah `farm/{farm}/reminders/*` (guard `auth`) dan `staff/reminders/*` (guard `staff`):

- `GET /farm/{farm}/reminders` — index, daftar reminder aktif milik user (sebagai pembuat atau target).
- `GET /farm/{farm}/reminders/create` — form pembuatan.
- `POST /farm/{farm}/reminders` — simpan reminder + targets + occurrence pertama.
- `GET /farm/{farm}/reminders/{reminder}` — detail reminder (hanya pembuat/target).
- `GET /farm/{farm}/reminders/{reminder}/edit` — form edit (hanya pembuat).
- `PUT /farm/{farm}/reminders/{reminder}` — update (hanya pembuat).
- `DELETE /farm/{farm}/reminders/{reminder}` — soft delete (hanya pembuat).
- `GET /farm/{farm}/reminders/calendar` — kalender occurrence sebulan.
- `POST /farm/{farm}/reminders/occurrences/{occurrence}/done` — tandai done.
- `POST /farm/{farm}/reminders/occurrences/{occurrence}/skip` — tandai skipped.

Route `staff/reminders/*` mengikuti pola yang sama dengan guard `staff`.

Push subscription: route PWA yang ada di-extend agar staff juga bisa mendaftarkan FCM token.

## Validasi & Error Handling

- Form Request memvalidasi:
  - `title` (required, string, max 255)
  - `body` (required, string)
  - `starts_at` (required, date, after:now untuk reminder baru)
  - `recurrence` (nullable, struktur sesuai tipe)
  - `advance_notify_minutes` (nullable, integer, min 1)
  - `target_mode` (`self` | `all` | `specific`)
  - `target_ids` (array, required saat `specific`)
- Policy `ReminderPolicy`:
  - `create`/`store`: pembuat adalah member farm aktif + target lolos hierarki.
  - `view`: pembuat atau salah satu target.
  - `update`/`destroy`: hanya pembuat.
- Staff hanya melihat reminder di farm-nya sendiri.
- Penjabaran recurrence dijaga agar tidak menghasilkan occurrence duplikat (unique index `(reminder_id, scheduled_at)`).

## Struktur Berkas (perkiraan)

- Migrasi: `create_reminders_table`, `create_reminder_targets_table`, `create_reminder_occurrences_table`, `modify_push_subscriptions_table`.
- Model: `App\Models\Reminder`, `App\Models\Reminder\ReminderTarget`, `App\Models\Reminder\ReminderOccurrence`.
- Enum: `ReminderStatus` (pending/done/skipped), `RecurrenceType` (none/interval/weekly/monthly).
- Service: `ReminderDispatchService` (penjabaran recurrence + pengiriman), `ReminderTargetResolver` (resolve mode target + validasi hierarki).
- Command: `reminders:dispatch`.
- Controller: `ReminderController` (guard auth), `Staff\StaffReminderController` (guard staff).
- Form Request: `StoreReminderRequest`, `UpdateReminderRequest`.
- Policy: `ReminderPolicy`.
- View: `reminders/index`, `reminders/create`, `reminders/show`, `reminders/edit`, `reminders/calendar`.

## Testing

- **Unit**:
  - Penjabaran recurrence: `none`, `interval`, `weekly`, `monthly`, batas jumlah occurrence.
  - Perhitungan `advance_notify_at`.
  - Validasi hierarki target (owner→semua, manager→manager+staff, staff→staff).
- **Feature**:
  - Store reminder dengan mode target `self`/`all`/`specific` + aturan hierarki.
  - Hanya pembuat yang bisa edit/hapus.
  - Toggle done/skipped per occurrence.
  - Command `reminders:dispatch` mengirim push ke target yang tepat, tidak double-send.
  - Staff hanya akses reminder di farm sendiri.
  - Migrasi `push_subscriptions` memindahkan data lama dengan benar.

## Non-Goals

- Google Calendar sync (struktur data dijaga agar mendukungnya, tidak dibangun sekarang).
- Notifikasi in-app / database notification.
- Email reminder.
- Pengaturan interval kustom di luar ketiga tipe recurrence (interval hari, weekly, monthly).
