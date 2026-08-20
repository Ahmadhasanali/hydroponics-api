# Desain: Halaman Pengingat sebagai Pusat Notifikasi

Tanggal: 2026-08-20
Status: Disetujui

## Ringkasan

Menjadikan halaman navbar **"Pengingat"** (`/reminders`) sebagai pusat notifikasi pengingat:

1. Hanya menampilkan pengingat yang notifikasinya **belum terkirim** ke target.
2. Card pengingat dapat diklik → menuju halaman detail pengingat.
3. Pengingat dapat ditambahkan langsung dari halaman tersebut dengan memilih farm.
4. Halaman pengingat per-farm tetap menampilkan semua pengingat (manajemen).

## Aturan Tampil (Visibilitas)

Saat `?upcoming=1`, sebuah pengingat **ditampilkan** jika:

- `is_active = true`, DAN
- terdapat minimal satu occurrence berstatus `Pending` dengan `notified_at` kosong DAN `advance_notified_at` kosong, DAN
- salah satu dari:
  - belum ada occurrence yang pernah di-notify (`notified_at` atau `advance_notified_at` pernah terisi) — "siklus pertama", ATAU
  - occurrence pending tersebut terjadwal `<= now + reappear_days`.

Aturan turunan:

- **Non-berulang:** tampil sejak dibuat; hilang setelah notif occurrence-nya terkirim.
- **Berulang:** tampil sejak dibuat; hilang setelah notif terkirim; muncul kembali `reappear_days` hari sebelum occurrence berikutnya.

`reappear_days` = `config('reminders.reappear_days', 2)`.

## Perubahan API

### 1. Filter upcoming

`GET /api/v1/reminders?upcoming=1`

- `ReminderController@index` membaca `$request->boolean('upcoming')`.
- Saat `true`, terapkan filter visibilitas di atas via query pada relasi `occurrences`.
- Param `farm_id` tetap berfungsi secara independen.

### 2. Aksi occurrence untuk user

Di grup `auth:sanctum,user` (routing `routes/api.php`):

- `POST /api/v1/reminders/{reminder}/occurrences/{occurrence}/done`
- `POST /api/v1/reminders/{reminder}/occurrences/{occurrence}/skip`

Implementasi di `ReminderController`, otorisasi mengikuti pola `StaffReminderController`:

- Berhak jika `created_by_type = User` & `created_by_id = user.id` (pembuat), ATAU user termasuk target (`ReminderTarget` dengan `targetable_type = User`, `targetable_id = user.id`).
- Menggunakan `ReminderOccurrence::markDone()` / `markSkipped()`.

### 3. Konfigurasi

Tambahkan di `config/app.php`:

```php
'reminders' => [
    'reappear_days' => (int) env('REMINDER_REAPPEAR_DAYS', 2),
],
```

## Perubahan Frontend (hydroponics-web)

### Halaman global `/reminders`

- `useGlobalReminders` menambah `params: { upcoming: 1 }`.
- Card pengingat dibuat clickable → `navigate('/farms/$farmId/reminders/$reminderId')`.
- Tombol **Tambah Pengingat** → dialog pilih farm (pola `RecordSheet` pada `Navigation.tsx`) → buka `ReminderForm` dengan farm terpilih.
  - `ReminderForm` menerima `farmId` opsional untuk konteks global; saat tanpa farmId, form dibuat hanya setelah farm dipilih.

### Halaman detail pengingat

Route baru: `/farms/$farmId/reminders/$reminderId`

- Menampilkan: judul, isi, `starts_at`, farm, recurrence, daftar occurrence (mendatang & lampau) dengan badge status.
- Aksi: **Tandai selesai** / **Lewati** (occurrence pending, bila berhak), **Edit** (buka `ReminderForm`), **Hapus** (konfirmasi).
- Hook baru:
  - `useReminder(id)` — `GET /reminders/{id}`
  - `useOccurrenceDone(reminderId, occurrenceId)` — `POST .../done`
  - `useOccurrenceSkip(reminderId, occurrenceId)` — `POST .../skip`
- Setelah done/skip/edit/hapus: invalidasi query `['reminders']`.

### Halaman pengingat per-farm `/farms/$farmId/reminders`

- Card di `ReminderList` dibuat clickable → navigasi ke detail.
- Tetap menampilkan semua pengingat (tanpa `upcoming`).

## Pengujian

### API (tests/Feature/Reminder)

- **UpcomingFilterTest:**
  - Non-berulang belum terkirim → tampil.
  - Non-berulang sudah di-notify → tidak tampil.
  - Berulang siklus pertama → tampil.
  - Berulang occurrence terkirim, berikutnya > 2 hari → tidak tampil.
  - Berulang occurrence berikutnya `<= now + 2 hari` → tampil.
  - `upcoming` tidak mengubah hasil saat param tidak diberikan.
- **OccurrenceActionTest:**
  - Pembuat user → boleh done/skip.
  - User target → boleh done/skip.
  - User lain → 403.
  - Occurrence milik reminder farm lain → 403.

### Frontend

- `npm run build` dan typecheck berhasil.