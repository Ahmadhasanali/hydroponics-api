# Desain — Role Kebun (Owner/Manager) & Petugas Lapangan (Staff)

> **Status:** Approved — menunggu review user.
> **Tanggal:** 2026-08-03
> **Scope:** Sistem manajemen kebun — role member + entitas petugas lapangan terpisah.

---

## 1. Ringkasan

Menambahkan sistem role yang jelas pada kebun dan entitas **petugas lapangan (staff)** yang terpisah dari user terdaftar:

- **Owner** — pembuat/pemilik kebun, punya semua akses termasuk menghapus kebun dan transfer kepemilikan. Persis 1 per kebun.
- **Manager** — semua akses kecuali menghapus kebun dan transfer kepemilikan.
- **Staff (Petugas Lapangan)** — entitas terpisah (bukan user), terikat **1 kebun**, hanya melakukan transaksi di kebun yang ditunjuk.

Petugas yang ingin mendaftarkan kebun sendiri harus mendaftar sebagai user di sistem (akun terpisah dari akun staff).

---

## 2. Model Role

Role disimpan di pivot `farm_users.role` dengan nilai baru: `owner` | `manager`.

| Role | Hak |
|------|-----|
| `owner` | Semua akses, termasuk hapus kebun, transfer kepemilikan, kelola member & staff. Persis 1 per kebun. |
| `manager` | Semua kecuali hapus kebun & transfer kepemilikan. Bisa kelola member (tidak bisa sentuh owner), staff, tank, edit data kebun. |
| `staff` | Bukan user. Terikat 1 kebun. Input monitoring/nutrisi/pH + edit/hapus catatan sendiri + lihat laporan. |

### Migrasi role lama

Semua baris `farm_users` dengan role `'member'` diubah menjadi `'manager'`. Aman: belum ada data member selain owner (belum di-deploy).

---

## 3. Model Data

### Tabel baru: `staff`

| Kolom | Tipe | Catatan |
|-------|------|---------|
| `id` | bigint PK | |
| `farm_id` | FK → farms | cascadeOnDelete |
| `name` | string | Nama lengkap petugas |
| `username` | string | Kredensial login |
| `password` | string hashed | |
| `is_active` | boolean default true | Jika false, tidak bisa login |
| `timestamps` | | |
| `softDeletes` | | Hapus staff tidak menghapus riwayat transaksi |

**Unique index:** `(farm_id, username)` — username unik per kebun; antar kebun boleh sama.

Tidak ada pivot `farm_staff` — 1 akun staff = 1 kebun (keputusan desain).

### Perubahan tabel existing

- `farm_users.role`: nilai menjadi `owner` | `manager` (migrasi `member` → `manager`).
- `daily_monitorings`, `nutrient_additions`, `ph_down_logs`, `activity_logs`: tambah kolom `staff_id` nullable FK → `staff`. Transaksi diisi `user_id` **XOR** `staff_id`.
- `farms.name`: tambah unique index — menjamin lookup login staff tidak ambigu dan nama kebun tidak duplikat.

### Atribusi transaksi

Catatan transaksi menampilkan nama user (dari `users`) atau nama staff (dari `staff`). Logika display: `$record->user ?? $record->staff`.

---

## 4. Autentikasi Staff

### Guard

- Guard baru `staff` di `config/auth.php` dengan provider `staff` → model `App\Models\Staff`.
- `Staff` implements `Illuminate\Contracts\Auth\Authenticatable`, `HasFactory`, `SoftDeletes`.
- Sesi staff terpisah dari sesi user — login staff dan login user di browser yang sama tidak saling menimpa.

### Alur login staff (final)

1. Halaman `/staff/login` (middleware `guest:staff`), layout auth dengan judul "Login Petugas Lapangan" + link ke login user.
2. Form 3 field teks: **Nama Kebun**, **Username**, **Password** — tanpa dropdown daftar kebun (mencegah kebocoran daftar kebun).
3. Lookup gabungan:

```php
$farm = Farm::where('name', $input['farm_name'])->first();
$staff = Staff::where('farm_id', $farm?->id)
    ->where('username', $input['username'])
    ->first();
```

4. Verifikasi password (`Hash::check`) + `is_active`. Gagal → kembali dengan pesan generik "Nama kebun, username, atau password salah."
5. Sukses → `Auth::guard('staff')->login(...)`, regenerate session, redirect ke `staff.dashboard`.
6. Logout: POST `/staff/logout` → logout guard staff + invalidate session → `/staff/login`.

### Keamanan login

- Rate limit `throttle:login` (sama seperti login user).
- FormRequest validasi: `farm_name`, `username`, `password` required string.
- Pengguna yang sudah login user tidak bisa akses `/staff/login` dan sebaliknya.
- Staff dinonaktifkan ditolak: "Akun tidak aktif. Hubungi pemilik kebun."

---

## 5. UX Staff (Layout Terpisah)

Layout baru `resources/views/layouts/staff.blade.php` — disederhanakan, konteks 1 kebun (tanpa farm switcher):

- **Dashboard**: ringkasan tank + statistik kebun (PPM/pH/temp rata-rata).
- **Input transaksi**: Monitoring Harian · Nutrisi AB Mix · pH Down. Memilih **tank** dari daftar tank kebun (read-only, hanya nama/aktif).
- **Catatan Saya**: daftar transaksi milik staff — bisa edit/hapus.
- **Laporan**: rekap data kebun.

---

## 6. Controller

### Controller staff baru

| Controller | Fungsi |
|------------|--------|
| `StaffAuthController` | showLoginForm, login, logout (guard `staff`) |
| `StaffDashboardController` | dashboard ringkasan kebun |
| `StaffMonitoringController` | CRUD DailyMonitoring (khusus catatan sendiri utk edit/hapus) |
| `StaffNutrientAdditionController` | CRUD NutrientAddition (sama) |
| `StaffPhDownController` | CRUD PhDownLog (sama) |
| `StaffReportController` | laporan kebun |

Semua controller staff memuat kebun dari `auth('staff')->user()->farm` (tidak ada pilihan kebun).

### Perubahan controller user

- `FarmUserController`: form tambah member — user yang ditambahkan **selalu berperan `manager`** (role `owner` hanya diperoleh via transfer kepemilikan); section **kelola akun staff** (buat, reset password, nonaktifkan/aktifkan, hapus).
- `FarmPolicy`:
  - `view` → semua member kebun.
  - `update` → role `owner` | `manager`.
  - `delete` → role `owner`.
  - method `transferOwnership` → role `owner`.
- `TankController` + transaksi controller user: tambah otorisasi role (gap yang selama ini kosong):
  - Tank CRUD → owner/manager.
  - Transaksi create → owner/manager; edit/delete → owner/manager (siapa pun punya).
- `ActivityLogObserver`: deteksi guard — aksi dari staff mengisi `staff_id`; dari user mengisi `user_id`.

---

## 7. Matriks Otorisasi

| Aksi | Owner | Manager | Staff |
|------|:---:|:---:|:---:|
| Lihat kebun | ✅ | ✅ | ✅ (kebunnya sendiri) |
| Edit data kebun | ✅ | ✅ | ❌ |
| Hapus kebun | ✅ | ❌ | ❌ |
| Transfer kepemilikan | ✅ | ❌ | ❌ |
| Kelola member (tambah/hapus) | ✅ | ✅ (tidak bisa sentuh owner) | ❌ |
| Buat/kelola akun staff | ✅ | ✅ | ❌ |
| Tank CRUD | ✅ | ✅ | ❌ (tank read-only utk input) |
| Input monitoring/nutrisi/pH | ✅ | ✅ | ✅ |
| Edit/hapus catatan siapa pun | ✅ | ✅ | ❌ |
| Edit/hapus catatan sendiri | ✅ | ✅ | ✅ |
| Lihat laporan | ✅ | ✅ | ✅ |

---

## 8. Kasus Tepi

1. **Owner terakhir** tidak bisa dihapus/demote — kebun selalu punya persis 1 owner.
2. **Transfer kepemilikan**: owner menyerahkan ke user terdaftar (via fitur transfer) → target jadi owner, owner lama jadi manager. Dibungkus DB transaction. Menambah member selalu sebagai `manager`; satu-satunya jalan menjadi owner adalah transfer dari owner saat ini (menjaga persis 1 owner).
3. **Username staff duplikat** dalam satu kebun → ditolak validasi; antar kebun boleh sama (unique `(farm_id, username)`).
4. **Staff dinonaktifkan** (`is_active=false`) → tidak bisa login; catatan lama tetap tampil.
5. **Staff dihapus** → soft delete; catatan transaksi tetap tersimpan & tampil.
6. **Nama kebun duplikat** dicegah unique index `farms.name`.
7. **Atribusi**: `user_id` XOR `staff_id` — tidak pernah keduanya terisi.

---

## 9. Pengujian

- Feature tests auth staff: login sukses/gagal, salah nama kebun, staff nonaktif ditolak, logout.
- Feature tests role: manager tidak bisa hapus kebun / transfer ownership; manager bisa kelola member & staff; staff tidak bisa kelola member/staff.
- Feature tests transaksi staff: staff create, edit/hapus catatan sendiri, tidak bisa edit/hapus catatan orang lain; staff melihat hanya tank kebunnya.
- Feature tests member management: owner transfer ownership (owner lama → manager), owner terakhir tidak bisa dihapus, duplicate staff username per kebun.
- Unit test model Staff: unique `(farm_id, username)`.
- Test migrasi role: `member` → `manager`.

---

## 10. Non-Goals (YAGNI)

- Tidak ada pivot `farm_staff` (staff tidak multi-kebun).
- Tidak ada self-registration staff (akun dibuat owner/manager).
- Tidak ada reset password staff via email (reset manual oleh owner/manager).
- Tidak ada transfer ownership ke staff.
