# Integrasi Mailer Resend — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengaktifkan pengiriman email via Resend (transport `resend` bawaan Laravel) sehingga sistem siap mengirim email transaksional tanpa masuk spam.

**Architecture:** Transport `resend` sudah ada di `config/mail.php` dan `config/services.php`. Yang kurang: SDK `resend/resend-php` dan nilai env. Konfigurasi `.env` menyalakan mailer `resend`; test membuktikan transport ter-resolve ke `ResendTransport` dengan key terbaca dari env.

**Tech Stack:** Laravel 13 (PHP 8.5), Sail, PHPUnit 12, `resend/resend-php` (SDK), `ResendTransport` bawaan framework.

## Global Constraints

- Working dir semua perintah: root project `Hydroponic-Farm-Management-System_Laravel`, lewat Sail: `./vendor/bin/sail ...`.
- Jangan menambah dependency composer selain `resend/resend-php`.
- Test ditulis sebagai kelas PHPUnit (bukan Pest).
- Setelah mengubah file PHP, jalankan `./vendor/bin/sail bin pint --dirty --format agent`.
- Jangan commit API key; `RESEND_API_KEY` hanya di `.env` (sudah di `.gitignore`). `.env.example` memakai placeholder kosong.
- Nilai `RESEND_API_KEY` dan `MAIL_FROM_ADDRESS` disusulkan user saat implementasi — pakai placeholder bila belum tersedia.
- `phpunit.xml` sudah menetapkan `MAIL_MAILER=array` untuk semua test; default mailer test tidak diubah.

---

## Struktur File

| File | Tanggung jawab |
|------|----------------|
| `composer.json` / `composer.lock` | Tambah `resend/resend-php` (via `composer require`) |
| `.env` | `MAIL_MAILER=resend`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS/NAME` |
| `.env.example` | Placeholder `RESEND_API_KEY=` + contoh konfigurasi resend |
| `phpunit.xml` | Tambah `<env name="RESEND_API_KEY" value="re_test_123"/>` agar test key terbaca |
| `tests/Feature/Mail/ResendMailerTest.php` | Test baru: transport ter-resolve ke `ResendTransport`, key terbaca dari env |

---

### Task 1: Test mailer resend (TDD — tulis test dulu, harus gagal)

**Files:**
- Create: `tests/Feature/Mail/ResendMailerTest.php`
- Modify: `phpunit.xml` (tambah env `RESEND_API_KEY`)

**Interfaces:**
- Consumes: `Illuminate\Mail\Transport\ResendTransport` (bawaan framework), `Resend::client()` dari SDK (belum ada — itulah yang membuat test gagal).
- Produces:
  - Test `test_resend_transport_resolves()` membuktikan `app('mail.manager')->mailer('resend')->getSymfonyTransport()` mengembalikan `ResendTransport`.
  - Test `test_resend_api_key_reads_from_env()` membuktikan `config('services.resend.key')` === `re_test_123`.

- [ ] **Step 1: Tambah env key test ke `phpunit.xml`** — di blok `<php>`, setelah `<env name="MAIL_MAILER" value="array"/>`:

```xml
<env name="RESEND_API_KEY" value="re_test_123"/>
```

- [ ] **Step 2: Tulis test yang gagal** — `tests/Feature/Mail/ResendMailerTest.php`:

```php
<?php

namespace Tests\Feature\Mail;

use Illuminate\Mail\Transport\ResendTransport;
use Tests\TestCase;

class ResendMailerTest extends TestCase
{
    public function test_resend_transport_resolves(): void
    {
        $transport = app('mail.manager')->mailer('resend')->getSymfonyTransport();

        $this->assertInstanceOf(ResendTransport::class, $transport);
    }

    public function test_resend_api_key_reads_from_env(): void
    {
        $this->assertSame('re_test_123', config('services.resend.key'));
    }
}
```

- [ ] **Step 3: Jalankan test untuk memastikan GAGAL**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Mail/ResendMailerTest.php`
Expected: FAIL — error `Class "Resend" not found` (SDK belum terpasang) pada `test_resend_transport_resolves`.

---

### Task 2: Install SDK Resend

**Files:**
- Modify: `composer.json`, `composer.lock` (hasil `composer require`)

**Interfaces:**
- Consumes: Test Task 1 (transport harus ter-resolve).
- Produces: `Resend\Resend::client()` tersedia → `ResendTransport` bisa di-instantiate.

- [ ] **Step 1: Install SDK**

Run: `./vendor/bin/sail composer require resend/resend-php`
Expected: package `resend/resend-php` masuk ke `composer.json` + `composer.lock`.

- [ ] **Step 2: Jalankan test untuk memastikan PASS**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Mail/ResendMailerTest.php`
Expected: PASS (2 tests green).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Mail/ResendMailerTest.php phpunit.xml composer.json composer.lock
git commit -m "feat: integrasi mailer resend (SDK + test transport)"
```

---

### Task 3: Konfigurasi `.env` dan `.env.example`

**Files:**
- Modify: `.env` (tidak di-commit)
- Modify: `.env.example`

**Interfaces:**
- Consumes: SDK terpasang (Task 2).
- Produces: default mailer menjadi `resend` dengan key dan from address.

- [ ] **Step 1: Update `.env`** — ubah blok mail menjadi:

```
MAIL_MAILER=resend
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="<isi alamat domain terverifikasi, mis. no-reply@domain.com>"
MAIL_FROM_NAME="${APP_NAME}"
RESEND_API_KEY=re_xxxxxxxx
```

Catatan untuk executor: minta user mengisi `RESEND_API_KEY` dan `MAIL_FROM_ADDRESS` sebelum verifikasi manual. Bila belum ada, isi placeholder dan lanjut.

- [ ] **Step 2: Update `.env.example`** — blok mail menjadi (placeholder kosong):

```
MAIL_MAILER=resend
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS=""
MAIL_FROM_NAME="${APP_NAME}"
RESEND_API_KEY=
```

- [ ] **Step 3: Jalankan ulang test untuk memastikan tetap hijau**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Mail/ResendMailerTest.php`
Expected: PASS.

- [ ] **Step 4: Pint + Commit**

Run: `./vendor/bin/sail bin pint --dirty --format agent`
Run: `./vendor/bin/sail artisan config:clear`

```bash
git add .env.example
git commit -m "feat: konfigurasi mailer resend di .env.example"
```

---

### Task 4: Verifikasi manual (oleh user)

- [ ] **Step 1: Konfirmasi `RESEND_API_KEY` & `MAIL_FROM_ADDRESS` terisi di `.env`** — minta user memastikan key valid dan from address memakai domain terverifikasi Resend.

- [ ] **Step 2: Kirim email uji**

Run: `./vendor/bin/sail artisan tinker --execute 'Mail::raw("Tes integrasi Resend berhasil.", fn ($m) => $m->to("email-tujuan@contoh.com")->subject("Uji Mailer Resend"));'`
Expected: email terkirim; cek inbox (dan folder spam) penerima.

- [ ] **Step 3: Uji dari kode aplikasi (opsional)** — verifikasi bahwa `Notification::route('mail', ...)` atau `Mail::to(...)->send(...)` bekerja lewat jalur yang sama.

---

## Catatan Eksekusi

- Setelah implementasi selesai dan test hijau, tawarkan user menjalankan seluruh test suite: `./vendor/bin/sail artisan test --compact`.
- Verifikasi manual (Task 4) menunggu nilai ENV dari user; jangan menganggap sukses sebelum email benar-benar terkirim.
