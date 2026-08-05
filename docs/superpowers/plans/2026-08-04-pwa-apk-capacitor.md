# PWA → APK Capacitor + FCM Native — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membungkus PWA menjadi APK Android via Capacitor dengan FCM native push, instal langsung tanpa Play Store.

**Architecture:** Capacitor 7 WebView dengan `server.url` ke `https://hydroponic.ahmadhasan.my.id`. Push notifikasi via `@capacitor/push-notifications` — token Android masuk ke `push_subscriptions` yang sama, backend `PushNotificationService` tak berubah. `google-services.json` (`com.hydrofarm.app`) sudah tersedia.

**Tech Stack:** Capacitor 7, `@capacitor/push-notifications` 7, Android Gradle (minSdk 23, targetSdk 35), Java 21, Firebase (google-services.json existing), Vite 8 (existing build).

## Global Constraints

- Working dir: `Hydroponic-Farm-Management-System_Laravel`
- Semua perintah npm dijalankan tanpa Sail (host machine, bukan container)
- Semua perintah gradle dijalankan dari `android/` directory
- `ANDROID_HOME=/home/ali/Applications/android` dan `ANDROID_SDK_ROOT=$ANDROID_HOME` harus diset
- `google-services.json` SUDAH ada di root project, package_name `com.hydrofarm.app`
- Nol perubahan ke file Laravel (PHP, Blade, controller, route, config)
- Build APK: `assembleDebug` (debug keystore `~/.android/debug.keystore`)
- Folder `android/` di-commit (kecuali output build)
- Server live: `https://hydroponic.ahmadhasan.my.id`
- FCM native IN SCOPE (bukan deferred)
- npm run build menjalankan Vite build existing (tidak berubah)

---

### Task 1: Install Capacitor dependencies + capacitor.config.ts

**Files:**
- Create: `capacitor.config.ts`
- Modify: `package.json` (add 4 dependencies)

**Interfaces:**
- Consumes: — (task pertama)
- Produces:
  - `capacitor.config.ts` — export `CapacitorConfig` dengan `appId: 'com.hydrofarm.app'`, `webDir: 'public'`, `server.url: 'https://hydroponic.ahmadhasan.my.id'`, `server.cleartext: false`, `server.androidScheme: 'https'`, `android.allowMixedContent: false`
  - `package.json` — tambah `@capacitor/core`, `@capacitor/cli`, `@capacitor/android`, `@capacitor/push-notifications`

- [ ] **Step 1: Install Capacitor packages**

Run:
```bash
npm install @capacitor/core @capacitor/cli @capacitor/android @capacitor/push-notifications
```

Expected: packages terinstal di `node_modules/`, `package.json` dan `package-lock.json` terupdate.

- [ ] **Step 2: Verifikasi versi yang terinstal**

Run:
```bash
node -e "console.log(require('@capacitor/core/package.json').version)"
```

Expected: output versi 7.x (contoh: `7.2.0`).

- [ ] **Step 3: Tulis capacitor.config.ts**

File `capacitor.config.ts`:

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

- [ ] **Step 4: Verifikasi Capacitor bisa membaca config**

Run:
```bash
npx cap doctor
```

Expected: output menampilkan info app, termasuk `App ID: com.hydrofarm.app` (walaupun platform android belum ditambahkan — hanya verifikasi config terbaca).

- [ ] **Step 5: Commit**

```bash
git add capacitor.config.ts package.json package-lock.json
git commit -F - <<'EOF'
feat: add Capacitor config and dependencies

Co-authored-by: CommandCodeBot <noreply@commandcode.ai>
EOF
```

---

### Task 2: Capacitor init + add android platform + google-services.json

**Files:**
- Create: `android/` (seluruh folder, dari `npx cap add android` — kecuali output build)
- Modify: `.gitignore` (tambah exclude Gradle build output)

**Interfaces:**
- Consumes: `capacitor.config.ts` + `google-services.json` (Task 1 + existing di root)
- Produces:
  - `android/` folder dengan `gradlew`, `build.gradle`, `app/build.gradle`, `AndroidManifest.xml`
  - `android/app/google-services.json` — disalin dari root
  - Plugin `com.google.gms.google-services` terdaftar di `android/app/build.gradle` (auto oleh Capacitor)

- [ ] **Step 1: Set Android SDK environment**

Run:
```bash
export ANDROID_HOME=/home/ali/Applications/android
export ANDROID_SDK_ROOT=$ANDROID_HOME
echo "ANDROID_HOME=$ANDROID_HOME"
```

Expected: environment ter-set. (Nanti tambahkan ke `~/.bashrc` manual setelah build sukses.)

- [ ] **Step 2: Verifikasi SDK lengkap**

Run:
```bash
ls $ANDROID_HOME/platforms/ && ls $ANDROID_HOME/build-tools/ && ls $ANDROID_HOME/cmdline-tools/
```

Expected: directory `android-35` di `platforms/`, setidaknya satu versi di `build-tools/`, dan `cmdline-tools/` ada.

- [ ] **Step 3: Init Capacitor project**

Run:
```bash
npx cap init "Hydro Farm" com.hydrofarm.app --web-dir=public
```

Expected: konfirmasi config terbaca, tidak ada error.

- [ ] **Step 4: Tambah platform Android**

Run:
```bash
npx cap add android
```

Expected: folder `android/` terbuat dengan struktur Gradle. Verifikasi:
```bash
ls android/gradlew android/build.gradle android/app/build.gradle
```
Semua file harus ada.

- [ ] **Step 5: Salin google-services.json**

Run:
```bash
cp google-services.json android/app/
ls android/app/google-services.json
```

Expected: file tersalin.

- [ ] **Step 6: Verifikasi plugin google-services sudah aktif di build.gradle**

Run:
```bash
grep 'com.google.gms.google-services' android/app/build.gradle
```

Expected: output berisi `apply plugin: 'com.google.gms.google-services'` atau `id 'com.google.gms.google-services'`. Capacitor auto-detect `google-services.json` dan menambahkan plugin ini.

Jika tidak ada — tambahkan manual di bawah `apply plugin: 'com.android.application'`:
```gradle
apply plugin: 'com.google.gms.google-services'
```

- [ ] **Step 7: Sync Capacitor**

Run:
```bash
npx cap sync
```

Expected: aset `public/` disalin ke `android/app/src/main/assets/public/`. Verifikasi:
```bash
ls android/app/src/main/assets/public/icons/
```
Harus berisi `icon-192x192.png`, `icon-512x512.png`, `icon-maskable-512x512.png`.

- [ ] **Step 8: Update .gitignore**

Tambahkan di akhir `.gitignore`:
```
# Capacitor Android build output
android/.gradle/
android/build/
android/app/build/
android/capacitor-cordova-android-plugins/
```

- [ ] **Step 9: Commit**

```bash
git add android/ .gitignore
git commit -F - <<'EOF'
feat: add Android platform with Capacitor and google-services.json

Co-authored-by: CommandCodeBot <noreply@commandcode.ai>
EOF
```

---

### Task 3: capacitor-push.js — FCM native bridge

**Files:**
- Create: `resources/js/capacitor-push.js`
- Modify: `resources/js/app.js` (import capacitor-push.js)

**Interfaces:**
- Consumes:
  - `@capacitor/push-notifications` → `PushNotifications.addListener('registration', ...)`, `PushNotifications.addListener('pushNotificationReceived', ...)`, `PushNotifications.requestPermissions()`
  - `POST /push-subscriptions` (route: `push-subscriptions.store`) — body `{ fcm_token, platform }` dengan CSRF header
  - `DELETE /push-subscriptions` (route: `push-subscriptions.destroy`) — body `{ fcm_token }` dengan CSRF header
  - `window.Capacitor.isNative` — deteksi Capacitor runtime
- Produces:
  - Modul JS yang dieksekusi saat `DOMContentLoaded` di Capacitor native
  - Token FCM Android tersimpan di localStorage `fcm_token` + dikirim ke `POST /push-subscriptions`
  - Form `.js-logout-form` di-intercept → `DELETE /push-subscriptions` + hapus token + unregister

- [ ] **Step 1: Tulis resources/js/capacitor-push.js**

```js
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const pushEndpoint = () => {
    if (window.location.pathname.startsWith('/staff')) {
        return '/staff/push-subscriptions';
    }
    return '/push-subscriptions';
};

const registerNativeToken = async (PushNotifications) => {
    try {
        const permission = await PushNotifications.requestPermissions();

        if (permission.receive !== 'granted') {
            return;
        }

        await PushNotifications.register();
    } catch (error) {
        console.error('FCM native registration failed:', error);
    }
};

const setupNativePush = async () => {
    try {
        const { PushNotifications } = await import('@capacitor/push-notifications');

        PushNotifications.addListener('registration', async ({ value }) => {
            const endpoint = pushEndpoint();
            const token = value;
            localStorage.setItem('fcm_token', token);

            try {
                await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ fcm_token: token, platform: 'android' }),
                });
            } catch (error) {
                console.error('FCM token registration failed:', error);
            }
        });

        PushNotifications.addListener('registrationError', ({ error }) => {
            console.error('FCM registration error:', error);
        });

        PushNotifications.addListener('pushNotificationReceived', ({ notification }) => {
            if (notification.data?.url) {
                console.log('Push received:', notification.title, notification.data.url);
            }
        });

        await registerNativeToken(PushNotifications);
    } catch (error) {
        console.error('Capacitor push notifications init failed:', error);
    }
};

const cleanupNativeTokenOnLogout = (PushNotifications) => {
    document.addEventListener('submit', async (event) => {
        if (!(event.target instanceof HTMLFormElement)) {
            return;
        }

        if (!event.target.classList.contains('js-logout-form')) {
            return;
        }

        event.preventDefault();

        const token = localStorage.getItem('fcm_token');
        const endpoint = pushEndpoint();

        try {
            if (token) {
                await fetch(endpoint, {
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
            try {
                await PushNotifications.unregister();
            } catch {
                // ignore
            }
            event.target.submit();
        }
    });
};

window.addEventListener('DOMContentLoaded', () => {
    if (window.Capacitor?.isNative) {
        setupNativePush();
        import('@capacitor/push-notifications').then(({ PushNotifications }) => {
            cleanupNativeTokenOnLogout(PushNotifications);
        });
    }
});
```

- [ ] **Step 2: Import di app.js**

Di `resources/js/app.js`, tambahkan setelah `import './firebase';`:

```js
import './capacitor-push';
```

File `app.js` setelah edit (top section saja yang berubah):

```js
import { registerSW } from 'virtual:pwa-register';
import './firebase';
import './capacitor-push';

registerSW({ immediate: true });

window.addEventListener('DOMContentLoaded', () => {
    const sidebar                 = document.getElementById('sidebar');
    const desktopSidebarToggleBtn = document.getElementById('desktopSidebarToggleBtn');
    // ... (sisa file tidak berubah)
```

- [ ] **Step 3: Build frontend**

Run:
```bash
npm run build
```

Expected: Vite build sukses, `public/build/` terisi dengan `manifest.webmanifest`, `sw.js`, dan bundle JS.

- [ ] **Step 4: Sync Capacitor (salin build baru)**

Run:
```bash
npx cap sync
```

Expected: `android/app/src/main/assets/public/build/` berisi file hasil build baru.

- [ ] **Step 5: Commit**

```bash
git add resources/js/capacitor-push.js resources/js/app.js
git commit -F - <<'EOF'
feat: add Capacitor FCM native push bridge

Co-authored-by: CommandCodeBot <noreply@commandcode.ai>
EOF
```

---

### Task 4: Build APK + install + final commit

**Files:**
- Tidak ada file baru (output build APK di `android/app/build/outputs/`, di-gitignore)

**Interfaces:**
- Consumes: semua file dari Task 1-3
- Produces: `android/app/build/outputs/apk/debug/app-debug.apk`

- [ ] **Step 1: Pastikan ANDROID_HOME diset**

Run:
```bash
export ANDROID_HOME=/home/ali/Applications/android
export ANDROID_SDK_ROOT=$ANDROID_HOME
echo "$ANDROID_HOME"
```

- [ ] **Step 2: Build APK debug**

Run:
```bash
cd android && ./gradlew assembleDebug
```

Expected: `BUILD SUCCESSFUL`. Verifikasi APK:
```bash
ls -la app/build/outputs/apk/debug/app-debug.apk
```

- [ ] **Step 3: Cek ukuran APK**

Run:
```bash
ls -lh android/app/build/outputs/apk/debug/app-debug.apk
```

Expected: ~5-15 MB (debug APK lebih besar karena tidak di-minify seagresif release).

- [ ] **Step 4: Install APK ke HP (jika tersambung USB)**

Run:
```bash
adb install android/app/build/outputs/apk/debug/app-debug.apk
```

Expected: `Performing Streamed Install` → `Success`.

Jika HP tidak tersambung, skip step ini — APK bisa ditransfer via file share.

- [ ] **Step 5: Manual test checklist**

Setelah APK terinstal:
1. Buka app → muncul halaman login dari `https://hydroponic.ahmadhasan.my.id`
2. Login → masuk ke dashboard
3. Izin notifikasi muncul (Android prompt) → klik Allow
4. Verifikasi token masuk ke `push_subscriptions` (jika bisa akses DB):
   ```bash
   # Lihat token Android (platform=android)
   vendor/bin/sail artisan tinker --execute 'App\Models\PushSubscription::where("platform", "android")->pluck("fcm_token")'
   ```
5. Bottom nav tampil di mobile
6. Sidebar di desktop

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -F - <<'EOF'
feat: build APK debug with Capacitor

Co-authored-by: CommandCodeBot <noreply@commandcode.ai>
EOF
```

---

### Task 5: Regresi tes Laravel

**Files:**
- Tidak ada file baru

**Interfaces:**
- Consumes: seluruh codebase
- Produces: test suite PASS (nol perubahan dari sebelum plan)

- [ ] **Step 1: Jalankan seluruh test suite**

Run:
```bash
vendor/bin/sail artisan test --compact
```

Expected: semua test PASS (jumlah & nama test tidak berubah dari sebelum plan dijalankan).

- [ ] **Step 2: Jika ada test gagal, periksa**

Test yang bisa terpengaruh (seharusnya tidak):
- `tests/Unit/Models/PushSubscriptionTest.php` — test model existing, tidak disentuh
- `tests/Feature/PushSubscription/PushSubscriptionControllerTest.php` — endpoint tidak berubah

Jika test gagal, laporkan error ke user.

---

## Verifikasi Manual (post-eksekusi)

- [ ] `./gradlew assembleDebug` menghasilkan APK tanpa error
- [ ] APK terinstal di HP → halaman login server muncul
- [ ] Login → dashboard berfungsi normal
- [ ] Izin notifikasi FCM native muncul
- [ ] Bottom nav tampil di mobile, sidebar di desktop
- [ ] `cap sync` berhasil menyalin aset `public/`
- [ ] `manifest.webmanifest` dan icon tersedia di `android/app/src/main/assets/public/`
