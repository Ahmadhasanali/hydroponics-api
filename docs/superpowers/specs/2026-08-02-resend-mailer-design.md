# Integrasi Mailer Resend — Design

> **Status:** Approved
> **Date:** 2026-08-02
> **Project:** Hydroponic Farm Management System (Laravel 13)

## Latar Belakang & Tujuan

Aplikasi saat ini memakai `MAIL_MAILER=log` — email tidak benar-benar terkirim, hanya
dicatat ke log. Sistem ke depannya perlu mengirim email transaksional ke pengguna
(contoh: verifikasi akun, reset password) dengan deliverability tinggi dan tidak
masuk spam.

**Tujuan:**
1. Aktifkan pengiriman email via **Resend** (transport `resend` bawaan Laravel).
2. Email tidak masuk spam → butuh verifikasi domain di dashboard Resend (sudah dimiliki user).
3. Siap dipakai fitur apa pun yang membutuhkan email nanti (verifikasi/reset di luar scope).

## Keputusan Kunci

- **Penyedia: Resend.** Transport `resend` sudah ada di `config/mail.php` dan
  `config/services.php` bawaan framework. Hanya SDK `resend/resend-php` yang belum terpasang.
- **Cakupan minimal (config-only).** Tidak membuat Mailable contoh, command `mail:test`,
  service wrapper, maupun kolom `email` di database. User hanya membutuhkan konfigurasi.
- **Verifikasi manual** dilakukan user lewat contoh perintah tinker (`Mail::raw(...)`).
- **Test programatik** tetap dibuat (aturan proyek) untuk membuktikan mailer ter-resolve
  ke `ResendTransport` dengan konfigurasi yang benar.
- Nilai **ENV `RESEND_API_KEY` dan `MAIL_FROM_ADDRESS` disusulkan user** saat implementasi.

## Lingkup

### In Scope
- Install dependency `resend/resend-php` (SDK Resend).
- Set `.env`: `MAIL_MAILER=resend`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`,
  `MAIL_FROM_NAME`.
- Update `.env.example` dengan placeholder untuk variabel yang sama.
- Satu feature test yang membuktikan konfigurasi mailer benar (`Mail::fake()` +
  assert transport `ResendTransport`, `mail.default` = `resend`, key terbaca dari env).

### Out of Scope / Deferred
- Alur verifikasi email & reset password.
- Penambahan kolom `email` di tabel `users`.
- Mailable contoh / command `mail:test` / service wrapper (`EmailService`).
- Verifikasi domain di dashboard Resend (dilakukan user).

## Arsitektur

```
┌──────────────────────────────────────────────────────────┐
│  KONFIGURASI                                              │
│  • .env          MAIL_MAILER=resend, RESEND_API_KEY,     │
│                  MAIL_FROM_ADDRESS/NAME                   │
│  • .env.example  placeholder variabel yang sama           │
├──────────────────────────────────────────────────────────┤
│  LARAVEL MAIL (existing)                                  │
│  • config/mail.php  mailer 'resend' (transport resend)    │
│  • config/services.php  resend.key ← RESEND_API_KEY       │
│  • ResendTransport (bawaan framework)                     │
├──────────────────────────────────────────────────────────┤
│  SDK                                                            │
│  • resend/resend-php (dependency baru)                   │
└──────────────────────────────────────────────────────────┘
```

## Detail Komponen

### 1. Dependency
- `composer require resend/resend-php` — SDK Resend, dibutuhkan oleh
  `ResendTransport` bawaan Laravel (`Resend::client($key)`).

### 2. Konfigurasi `.env`
```env
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=<alamat@domain-terverifikasi>
MAIL_FROM_NAME="Hydroponic-Farm-Management-System_Laravel"
RESEND_API_KEY=re_xxxx
```

### 3. Konfigurasi `.env.example`
- Salin baris yang sama dengan placeholder kosong (mis. `RESEND_API_KEY=`,
  `MAIL_FROM_ADDRESS=""`) agar developer lain tahu variabel yang wajib diisi.

## Alur Data

1. Kode aplikasi memanggil fasilitas Mail Laravel (`Mail::to(...)->send(...)`,
   `Notification::route('mail', ...)`, dll).
2. `MailManager::createResendTransport()` membaca key dari `services.resend.key`
   dan membungkus `Resend::client()` dalam `ResendTransport`.
3. `ResendTransport` memanggil Resend API → email terkirim dari domain terverifikasi.
4. Header `X-Resend-Email-ID` ditambahkan ke pesan sebagai referensi.

## Error Handling

- API key salah / kuota habis / network error → `TransportException` dilempar oleh
  `ResendTransport`; konsumen Mail/Queue menanganinya sesuai pola Laravel (retry job queue).
- Gagal verifikasi domain di dashboard Resend → email masuk spam/tolak; ini tanggung
  jawab setup user di dashboard, bukan kode.

## Testing

- **Feature test baru** (mis. `tests/Feature/Mail/ResendMailerTest.php`):
  - `Mail::fake()` → resolusi mailer via `app('mail.manager')->mailer('resend')`
    mengembalikan instance `ResendTransport`.
  - `config('mail.default')` === `resend` (dengan env di-set via `putenv`/`config()->set`
    dalam test atau `.env.testing`).
  - `config('services.resend.key')` terbaca dari `RESEND_API_KEY`.
- **Manual check (user):** jalankan
  `vendor/bin/sail artisan tinker --execute 'Mail::raw("Test Resend", fn ($m) => $m->to("email-anda@x.com")->subject("Uji"));'`
  lalu cek inbox (dan folder spam) setelah `RESEND_API_KEY` diisi.

## Konfigurasi ENV

| Variable | Keterangan | Status |
|---|---|---|
| `MAIL_MAILER` | `resend` (default `log`) | Diset |
| `RESEND_API_KEY` | API key Resend | **Disusulkan user** |
| `MAIL_FROM_ADDRESS` | Alamat pengirim (domain terverifikasi) | **Disusulkan user** |
| `MAIL_FROM_NAME` | Nama pengirim | Default |

Dependency baru: `resend/resend-php` (composer).

## Risiko / Catatan

- Verifikasi domain di dashboard Resend wajib selesai agar email tidak masuk spam.
- `.env` tidak di-commit (sudah di `.gitignore`); key hanya ada di lingkungan lokal/produksi.
- Email verifikasi/reset password belum dibangun — integration ini menyiapkan infrastruktur saja.
