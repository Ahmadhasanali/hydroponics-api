# PWA Mobile (Installable + Push Notifikasi) — Design

> **Status:** Approved
> **Date:** 2026-08-01
> **Project:** Hydroponic Farm Management System (Laravel 13)

## Latar Belakang & Tujuan

Aplikasi saat ini sudah mobile-responsive (Blade + Tailwind 4 via Vite), tapi belum bisa
di-install sebagai aplikasi di HP Android dan belum punya push notification. Petugas
lapangan perlu akses cepat ke pencatatan PPM/pH dengan pengalaman yang mendekati native.

**Tujuan:**
1. Aplikasi menjadi **installable PWA** di HP Android → icon home screen, window standalone, fast-load.
2. **Push notification** via **FCM (Firebase Cloud Messaging)** untuk Android:
   - Pengingat monitoring harian (PPM & pH).
   - Notifikasi aktivitas anggota farm.
3. **Bottom navigation bar** khusus mobile dengan **hamburger dihapus di mobile**; link sekunder dipindah ke halaman **Profil** (baru).
4. Tidak publish ke Play Store untuk sekarang.

## Keputusan Kunci

- **Pendekatan push: Hibrida.** FCM eksplisit untuk Android Chrome (delivery lebih andal saat Chrome di-force-close) lewat `firebase-messaging-sw.js`. Jalur VAPID web push untuk **iOS di-defer** (di luar scope saat ini).
- **FCM gratis** (Spark plan, tanpa kartu kredit, tanpa biaya per-message) — dikonfirmasi dari dokumentasi resmi Firebase.
- **Build tooling:** `vite-plugin-pwa` terintegrasi dengan `vite.config.js` existing; generate manifest + service worker (Workbox) otomatis saat build.
- **Tidak menambah Livewire/dependency frontend besar.** PWA murni dari sisi build tooling + service worker + modul JS kecil.
- Nilai **ENV FCM akan disusulkan** oleh user (placeholder dipakai dulu saat implementasi).

## Lingkup

### In Scope
- Manifest PWA + icons + service worker (pre-cache app shell → fast-load).
- Integrasi FCM: frontend (firebase SDK + service worker) dan backend (simpan token, kirim notif).
- Push notification: pengingat harian (scheduler) + aktivitas anggota farm (observer → queue).
- Bottom nav mobile (Dashboard, Catat, Riwayat, Profil).
- Hapus hamburger di mobile; sidebar menjadi desktop-only.
- Halaman Profil baru (hub link sekunder + info user + logout).
- Testing + deployment notes (Cloudflare Tunnel + scheduler).

### Out of Scope / Deferred
- Offline data entry & sync (input tetap butuh internet).
- iOS PWA push (jalur VAPID — deferred).
- Push notifikasi alert PPM/pH di luar range (roadmap).
- Publish ke Play Store.

## Arsitektur

```
┌────────────────────────────────────────────────────────┐
│  LAYER PWA (frontend)                                   │
│  • vite-plugin-pwa → manifest.json + SW Workbox         │
│  • Firebase SDK (messaging) + firebase-messaging-sw.js  │
│  • resources/js/firebase.js (token lifecycle)           │
│  • partials/bottom-nav.blade.php (mobile only)          │
│  • Halaman Profil (hub link sekunder)                   │
├────────────────────────────────────────────────────────┤
│  LAYER NOTIFIKASI (backend)                             │
│  • Migration push_subscriptions + model PushSubscription │
│  • Endpoint POST/DELETE /push-subscriptions (auth)      │
│  • app/Services/PushNotificationService (FCM HTTP v1)    │
│  • Command notify:daily-monitoring (scheduler 08:00)     │
│  • Observers: DailyMonitoring/NutrientAddition/         │
│    PhDownLog → NotifyFarmActivity (queue)               │
├────────────────────────────────────────────────────────┤
│  INFRA (home-lab)                                       │
│  • Cloudflare Tunnel → HTTPS domain (existing)          │
│  • Scheduler: schedule:work / cron schedule:run         │
│  • Queue worker (sudah ada via composer run dev / jobs) │
└────────────────────────────────────────────────────────┘
```

## Detail Komponen

### 1. PWA Layer (vite-plugin-pwa)

- Tambah dependency `vite-plugin-pwa`.
- `vite.config.js`: tambah plugin dengan config:
  - `registerType: 'autoUpdate'` — SW auto-update, pengguna selalu dapat versi terbaru.
  - `manifest`: `name` = "Hydroponic Farm Management", `short_name` = "Hydro Farm",
    `start_url` = `/dashboard`, `display` = `standalone`, `theme_color` + `background_color`
    mengikuti palet app (`#f8f6f2` bg / `#ffce54` aksen), `icons` (192 & 512 px + maskable).
  - Precache via Workbox: cache built assets + app shell (HTML halaman utama).
- Icons: butuh asset icon PNG 192 & 512 (maskable). **User perlu menyediakan / approve icon** (bisa derive dari logo droplet `bi-droplet-half` yang dipakai).
- Meta: `<meta name="theme-color">`, `<link rel="manifest">`, `<link rel="apple-touch-icon">` di `layouts/app.blade.php`.
- `viewport`: tambah `viewport-fit=cover` agar safe-area handling berfungsi.

### 2. FCM Frontend

- Tambah dependency npm `firebase` (impor hanya `messaging`).
- `public/firebase-messaging-sw.js` — service worker FCM (wajib di scope root, di-serve apa adanya).
- `resources/js/firebase.js`:
  - Init Firebase app dari env `VITE_FIREBASE_*`.
  - Minta `Notification.requestPermission()` + dapatkan token via `getToken(messaging, {vapidKey})`.
  - Kirim token ke `POST /push-subscriptions` (dengan CSRF).
  - `onTokenRefresh` → update token ke server.
  - Tangani `onMessage` (notifikasi saat app terbuka).
  - Registrasi hanya di halaman setelah login.
- `auth.js` (existing) atau modul terpisah: saat logout → `DELETE /push-subscriptions`.

### 3. Backend Push

- **Migration `push_subscriptions`:**
  - `id`, `user_id` (FK), `fcm_token` (unique), `platform` (default 'android'), `device_info` (nullable), timestamps.
- **Model `PushSubscription`** — `belongsTo(User)`.
- **Routes** (auth):
  - `POST /push-subscriptions` — simpan/update token.
  - `DELETE /push-subscriptions` — hapus token.
- **Package:** `kreait/firebase-php` (FCM HTTP v1 API, pakai service account JSON). Service account disimpan di path config/env.
- **`app/Services/PushNotificationService.php`:**
  - `sendToUser(User $user, string $title, string $body, ?string $url): void`
  - Loop semua token user; hapus token `UNREGISTERED`/invalid secara otomatis.
  - Gagal → catch + log; tidak pernah mengganggu alur utama.

### 4. Trigger Notifikasi

**a) Pengingat monitoring harian:**
- Command baru `notify:daily-monitoring`.
- Jadwal via Laravel scheduler: `->dailyAt(config('app.daily_reminder_hour', '08:00'))`.
- Kirim ke semua user yang punya minimal 1 token: *"Waktunya monitoring — catat PPM & pH tangki hari ini"*.
- Idempoten: catat last-run di tabel/flag agar tidak dobel kirim jika command terpaksa dijalankan ulang.

**b) Aktivitas anggota farm (Observer → Queue):**
- Observers baru pada `created` (model di namespace `App\Models\Farm\`):
  - `DailyMonitoringObserver`
  - `NutrientAdditionObserver`
  - `PhDownLogObserver`
- Dispatch job `NotifyFarmActivity` (`ShouldQueue`): kirim ke pemilik farm + member lain (kecuali pencatat).
  - Contoh pesan: *"Ali mencatat PPM tangki A: 850"*.
- Queue worker diperlukan (proses `queue:work` — sudah dipakai di `composer run dev`; perlu dipastikan di produksi).

### 5. Bottom Navigation (Mobile)

- Partial `resources/views/partials/bottom-nav.blade.php` dirender di `layouts/app.blade.php`, hanya `lg:hidden`.
- Konten utama diberi `pb-20 lg:pb-*` agar tidak tertutup nav.
- Item:
  1. **Dashboard** → `route('dashboard')`
  2. **Catat** → quick-add: menu/sheet ke create PPM, pH, Nutrient (AB Mix), pH Down
  3. **Riwayat** → `route('daily-monitoring.index')`
  4. **Profil** → `route('profile')`
- Aktif state via `request()->routeIs(...)` (pola yang sudah dipakai di sidebar).
- Gunakan `env(safe-area-inset-bottom)` untuk menghindari gesture bar.
- Icon: bootstrap-icons (sudah terpasang).

### 6. Hamburger di Mobile Dihapus

- Tombol hamburger (`openSidebarBtn`) tidak dirender di mobile (atau dirender `lg:` saja).
- Sidebar off-canvas + `mobileSidebarOverlay` menjadi **desktop-only** (`lg:`), mobile tidak lagi `-translate-x-full` behaviour.
- `app.js`: logika open/close mobile dihapus/di-simplify; logika collapse desktop dipertahankan.
- Sidebar desktop tetap memuat semua link (tidak berubah).

### 7. Halaman Profil (Baru)

- Route `profile` → controller sederhana (view).
- Isi:
  - Info user (nama, email, role farm).
  - Daftar link sekunder (yang tidak ada di bottom nav): **Farm**, **Tank**, **Reports** (Monitoring / AB Mix / pH Down), **Activity Logs**.
  - Tombol **Logout** (form POST `route('logout')`).

## Alur Data

1. **Registrasi device:**
   User login → halaman dimuat → `firebase.js` minta izin & token → `POST /push-subscriptions` → token tersimpan di DB.
2. **Pengingat harian:**
   Scheduler jam 08:00 → command → `PushNotificationService::sendToUser` (tiap user) → FCM → device Android.
3. **Aktivitas farm:**
   User catat record → observer → job queue → `sendToUser` (pemilik + member lain) → FCM.
4. **Logout:** frontend hapus token (`DELETE /push-subscriptions`).

## Error Handling

- Permission notif ditolak → banner informatif di halaman Profil, bukan error crash.
- Token expired/`UNREGISTERED` → dihapus otomatis dari DB saat send gagal.
- Gagal kirim FCM (salah config/network) → log + silent; tidak memblokir input/user request.
- Scheduler mati / container restart → notif pengingat terlewat; tetap fail-safe (tidak menghalangi fungsi aplikasi).
- Service worker gagal update → `autoUpdate` + Workbox cleanup otomatis.

## Testing

- **Feature test:** `POST/DELETE /push-subscriptions` (auth required, validasi token unik).
- **Unit test `PushNotificationService`** (mock FCM client):
  - Token valid → sukses.
  - `UNREGISTERED` → token dihapus dari DB.
  - Semua token gagal → exception tertangkap, log tercatat.
- **Feature test observer:** buat record (`DailyMonitoring`/`NutrientAddition`/`PhDownLog`) → job `NotifyFarmActivity` di-dispatch (fake queue); penerima = pemilik + member lain, bukan pencatat.
- **Test command scheduler:** dispatch + kirim (mock FCM), verifikasi idempoten.
- **Manual checklist PWA:**
  - Lighthouse PWA audit pass.
  - Install via Chrome Android "Add to Home Screen".
  - App shell tampil saat offline (data entry tetap butuh internet).
  - Notifikasi terkirim saat Chrome ditutup.
  - Bottom nav tampil hanya di mobile; sidebar hanya di desktop; hamburger tidak muncul di mobile.

## Deployment (Home-lab + Cloudflare Tunnel)

- HTTPS via Cloudflare Tunnel (existing) → domain publik ✓.
- **Scheduler:** pastikan ada proses `php artisan schedule:work` atau cron host `* * * * * php artisan schedule:run` di container Laravel. Tambahkan ke `compose.yaml` (service baru/command) atau dokumentasi setup.
- **Queue worker** untuk job notifikasi (sudah ada pola; pastikan di produksi).
- Build produksi: `npm run build` → vite-plugin-pwa output manifest + SW ke `public/build`.
- `APP_URL` produksi = domain Cloudflare (bukan localhost) agar scope SW & manifest benar.

## Konfigurasi ENV

| Variable | Keterangan | Status |
|---|---|---|
| `VITE_FIREBASE_API_KEY` | Firebase web API key | **Disusulkan user** |
| `VITE_FIREBASE_PROJECT_ID` | Firebase project ID | **Disusulkan user** |
| `VITE_FIREBASE_MESSAGING_SENDER_ID` | Sender ID | **Disusulkan user** |
| `VITE_FIREBASE_APP_ID` | Firebase app ID | **Disusulkan user** |
| `VITE_FIREBASE_VAPID_KEY` | VAPID key (pairing key) | **Disusulkan user** |
| `FCM_SERVICE_ACCOUNT_JSON` | Path/isi service account JSON | **Disusulkan user** |
| `DAILY_REMINDER_HOUR` | Jam pengingat harian (default `08:00`) | Bisa default |

Dependency baru: `vite-plugin-pwa`, `firebase` (npm); `kreait/firebase-php` (composer).

## Risiko / Catatan

- **iOS:** push PWA iOS butuh jalur VAPID (deferred). PWA tetap bisa di-install di iPhone tapi tanpa push sampai fase berikutnya.
- **Android force-close:** FCM meningkatkan peluang delivery, tapi jika user swipes Chrome dari recents sebelum 1 notif pertama terkirim, ada kemungkinan token tak terdaftar penuh. Document best practice ke user.
- Nilai ENV FCM tidak tersedia saat ini → implementasi memakai placeholder + dokumentasi; notifikasi belum bisa diuji end-to-end sampai user mengisi ENV.
