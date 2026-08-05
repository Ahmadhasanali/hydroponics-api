# PWA → APK Capacitor (Personal Use) — Design

> **Status:** Approved
> **Date:** 2026-08-04
> **Project:** Hydroponic Farm Management System (Laravel 13)
> **Prerequisite:** PWA Mobile spec (`2026-08-01-pwa-mobile-design.md`) — sudah terimplementasi

## Latar Belakang & Tujuan

PWA sudah berjalan (manifest, service worker, bottom-nav, FCM web), tapi masih diakses via
browser. Tujuan fase ini: **membungkus PWA menjadi APK Android via Capacitor** untuk
personal use, diinstal langsung di HP tanpa Play Store.

**Server sudah deploy** di `https://hydroponic.ahmadhasan.my.id` (Cloudflare Tunnel).
Tidak ada perubahan ke kode Laravel.

**Tujuan:**
1. APK bisa di-build di mesin development yang ada (Java 21 + Android SDK lengkap).
2. APK memuat WebView penuh ke server — semua UI/auth/data dari server.
3. Tidak ada kode Laravel yang berubah; hanya tambah file Capacitor + folder `android/`.

## Keputusan Kunci

- **Pendekatan:** Capacitor 7 + Gradle — APK native WebView, bukan TWA/WebAPK.
- **Server:** URL `https://hydroponic.ahmadhasan.my.id` di `capacitor.config.ts` → `server.url`.
  Ganti server = edit config + `cap sync` (tanpa rebuild Gradle).
- **WebView mode:** murni ke URL tanpa JS bridge (tidak pakai plugin Capacitor native
  di fase 1). App web berjalan persis seperti di browser.
- **FCM native:** **deferred.** Struktur APK siap, tinggal tambah Android app di Firebase
  console + `google-services.json` + `@capacitor/push-notifications` nanti.
  Backend `PushNotificationService` tak berubah.
- **Keystore:** debug (`~/.android/debug.keystore`) — personal, sideload.
- **Folder `android/`:** di-commit (kecuali output build), supaya `cap sync` + gradle cukup.
- **Kode Laravel:** zero perubahan.

## Lingkup

### In Scope
- Install Capacitor (`@capacitor/core`, `@capacitor/cli`, `@capacitor/android`) devDeps
- `capacitor.config.ts` — appId `com.hydrofarm.app`, server `https://hydroponic.ahmadhasan.my.id`
- `npx cap add android` → generate folder `android/`
- `npx cap sync` → salin `public/` ke `android/app/src/main/assets/public/`
- Set `ANDROID_HOME` + `ANDROID_SDK_ROOT` environment
- `./gradlew assembleDebug` → hasilkan `app-debug.apk`
- `.gitignore`: exclude output Gradle build
- Verifikasi APK terinstal via ADB
- Commit semua file baru

### Out of Scope / Deferred
- FCM native (`@capacitor/push-notifications` + `google-services.json`)
- Keystore release + signing production
- Splash screen / status bar native
- Offline fallback / custom error page WebView
- Publish ke Play Store
- iOS platform

## Arsitektur

```
Laravel app (PWA) ──────── build ────> public/build (manifest + sw.js)
      │
      ├─ npm install @capacitor/core @capacitor/cli @capacitor/android
      │
      ├─ npx cap init "Hydro Farm" com.hydrofarm.app
      │
      ├─ npx cap add android   (folder android/ — native project)
      │
      ├─ npx cap sync          (salin public/ → android/app/src/main/assets/public/)
      │
      └─ cd android && ./gradlew assembleDebug
             │
             └─ android/app/build/outputs/apk/debug/app-debug.apk
```

- **WebView memuat `server.url`** dari `capacitor.config.ts` — app web berjalan penuh
  (session, auth, Blade+Vite). Tanpa JS bridge.
- **Satu source of truth server:** `capacitor.config.ts` — ganti URL = edit + `cap sync`,
  tanpa rebuild Gradle.
- **FCM native:** ditambahkan nanti sebagai plugin Capacitor; server backend tetap pakai
  `kreait/firebase-php` yang sama, token Android dan token web tersimpan di
  `push_subscriptions` yang sama.

## Detail Komponen

### 1. Capacitor Config (`capacitor.config.ts`)

```ts
import { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.hydrofarm.app',
  appName: 'Hydro Farm',
  webDir: 'public',
  server: {
    url: 'https://hydroponic.ahmadhasan.my.id',
    cleartext: false,
    androidScheme: 'https',
  },
  android: {
    allowMixedContent: false,
  },
};

export default config;
```

URL `https://hydroponic.ahmadhasan.my.id` adalah server produksi via Cloudflare Tunnel.
APK memuat langsung dari URL tersebut — tidak ada file offline di dalam APK.

### 2. WebView Behavior

Murni WebView ke URL server — **tanpa JS bridge** ke Capacitor plugins.
Semua UI/CSS/JS dari server (Blade + Vite). Auth & session cookie Laravel jalan normal.

**Dev testing (tanpa server live — tidak diperlukan lagi):**

- Ubah `server.url` ke `http://<IP-PC>:8081` (HP via WiFi) atau `http://10.0.2.2:8081`
  (emulator Android)
- Sertakan `network_security_config.xml` izin cleartext untuk localhost
- Pastikan Sail berjalan di PC dan HP terkoneksi

### 3. Gradle Project (`android/`)

Dihasilkan oleh `npx cap add android`:

- **minSdkVersion 23** (Android 6.0+)
- **targetSdkVersion 35** (Android 15)
- **compileSdkVersion 35**
- **Java 21**
- **Gradle 8.x** (bundled Capacitor)
- **Keystore:** debug keystore `~/.android/debug.keystore` (password `android`)

### 4. Struktur File

```
├── capacitor.config.ts          ← di-commit
├── package.json                 ← +@capacitor/core, @capacitor/cli, @capacitor/android
├── android/                     ← di-commit (kecuali output build)
│   ├── app/
│   │   ├── build.gradle
│   │   ├── src/main/
│   │   │   ├── AndroidManifest.xml
│   │   │   ├── res/           ← icons Android native
│   │   │   └── assets/public/ ← disalin dari public/ saat cap sync
│   ├── build.gradle
│   ├── gradlew
│   └── ...
├── public/                      ← sumber cap sync (manifest, icons, build/)
```

**Exclude dari commit:** `android/.gradle/`, `android/build/`, `android/app/build/`,
`android/capacitor-cordova-android-plugins/`

## Build Workflow

```bash
# 1. Install Capacitor
npm install @capacitor/core @capacitor/cli @capacitor/android

# 2. Init (sekali saja, jika belum ada android/)
npx cap init "Hydro Farm" com.hydrofarm.app --web-dir=public

# 3. Tambah platform Android
npx cap add android

# 4. Sync (salin assets ke android/app/src/main/assets/public/)
npx cap sync

# 5. Build APK debug
cd android && ./gradlew assembleDebug

# 6. APK di:
# android/app/build/outputs/apk/debug/app-debug.apk
```

### Prerequisites Build

```bash
export ANDROID_HOME=/home/ali/Applications/android
export ANDROID_SDK_ROOT=$ANDROID_HOME
```

## Error Handling (WebView)

| Kondisi | Penanganan |
|---|---|
| Server tidak reachable | WebView default error page "ERR_CONNECTION_REFUSED" — tidak ada fallback offline di fase 1 |
| TLS/SSL error | WebView pakai CA store sistem Android; cert Cloudflare Tunnel valid otomatis |
| Mixed content | Diblokir sesuai `allowMixedContent: false` |
| No internet | WebView error page |

## Testing

| Jenis | Cakupan |
|---|---|
| **Build verification** | `./gradlew assembleDebug` sukses → APK di output dir |
| **Capacitor sync** | Verifikasi `android/app/src/main/assets/public/` berisi file dari `public/` |
| **Manifest validation** | `manifest.webmanifest` ter-copy; icon tersedia |
| **Manual test** | `adb install app-debug.apk` → app terbuka dan menampilkan halaman login server |
| **Regresi Laravel** | `./vendor/bin/sail artisan test --compact` tetap PASS — nol perubahan di Laravel |

## Definisi Done

1. `./gradlew assembleDebug` menghasilkan APK tanpa error
2. `./vendor/bin/sail artisan test --compact` seluruh suite tetap hijau
3. `git status` bersih — hanya file baru Capacitor yang ter-track
4. `adb install` APK ke HP → app terbuka
5. Nol refactor ke Blade, controller, route, SW, atau config Laravel

## Dependensi Baru

| Package | Kategori | Versi |
|---|---|---|
| `@capacitor/core` | dependencies | ^7.x |
| `@capacitor/cli` | devDependencies | ^7.x |
| `@capacitor/android` | devDependencies | ^7.x |

## Risiko / Catatan

- **`appId: com.hydrofarm.app`** — jika kelak publish ke Play Store, harus ganti `appId` baru
  yang unik karena Play Console tidak menerima debug APK.
- **Android SDK path:** `ANDROID_HOME` dan `ANDROID_SDK_ROOT` harus diset manual. Pastikan
  build-tools dan platform (35) terinstal di SDK manager.
- **Capacitor v7** — verifikasi versi terbaru saat instal; v7 adalah target.
- **WebView vs Chrome:** WebView di APK tidak mendukung Web Push API seperti browser
  — FCM web tidak jalan di dalam APK. FCM native diperlukan untuk push notifikasi dalam APK
  (deferred).
