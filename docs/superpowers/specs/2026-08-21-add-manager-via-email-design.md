# Desain: Tambah Manager via Email di Farm

Tanggal: 2026-08-21
Status: Disetujui

## Ringkasan

Menambahkan alur **tambah/hapus manager** (User) di web via email yang sudah terdaftar, melengkapi Card Anggota yang saat ini hanya read-only di `hydroponics-web/src/routes/_authenticated/farms/$farmId/index.tsx:28-36`.

Pembedaan dengan Staff (pekerja lapangan):
- **Manager** = `users` via pivot `farm_users.role='manager'` — harus punya akun terdaftar (`users.email`), diinvite via `POST /api/v1/farms/{farm}/members`.
- **Staff** = entitas `staff` via `POST /api/v1/farms/{farm}/staff` dengan `username/password` (tetap pakai `name`/`username`, tidak pakai email).

Bugfix: API saat ini `StoreFarmUserRequest` validasi `exists:users,name` dan lookup `where('name', ...)` (`hydroponics-api/app/Http/Requests/StoreFarmUserRequest.php:33`, `hydroponics-api/app/Http/Controllers/FarmUserController.php:46`) tidak sesuai kebutuhan email untuk manager — akan diperbaiki menjadi `exists:users,email`.

## Arsitektur & Scope

Scope 2 repo, tidak buat service baru:
- `hydroponics-api` — patch request/controller, tidak ubah Staff flow.
- `hydroponics-web` — tambah hooks + Dialog di Card Anggota existing, tidak buat halaman baru `/members`.

Route tetap:
- `POST /api/v1/farms/{farm}/members` (`hydroponics-api/routes/api.php:65`)
- `DELETE /api/v1/farms/{farm}/members/{farmUser}` (`hydroponics-api/routes/api.php:66`)

## Perubahan API (hydroponics-api)

### 1. Validasi StoreFarmUserRequest

File: `app/Http/Requests/StoreFarmUserRequest.php`

```php
public function authorize(): bool {
  return $farm->users()->where('user_id', $this->user()->id)->wherePivotIn('role', ['owner','manager'])->exists();
}
public function rules(): array {
  return ['email' => ['required','string','email','exists:users,email']];
}
```

### 2. Controller

File: `app/Http/Controllers/FarmUserController.php:42-60`

```php
$user = User::where('email', $validated['email'])->first(); // sebelumnya where('name',...)
if (!$user) return error 422 'User dengan email tersebut tidak ditemukan.';
if ($farm->users()->where('user_id',$user->id)->exists()) return 422 'User tersebut sudah menjadi anggota farm.';
$farm->users()->attach($user->id, ['role'=>'manager']);
```

`destroy` tetap: `Gate::authorize('manageMembers')`, guard `owner` tidak bisa dihapus, tidak bisa hapus diri sendiri (`FarmUserController.php:65-83`).

## Perubahan Frontend (hydroponics-web)

### 1. Hooks baru

File: `src/features/farms/hooks/useFarms.ts` (ikuti pola `src/features/staff/hooks/useStaff.ts`)

```ts
export function useFarmMembers(farmId: number) // GET /farms/{id}/members
export function useAddFarmMember() // POST /farms/${farmId}/members {email}
export function useRemoveFarmMember() // DELETE /farms/${farmId}/members/${farmUserId}
// onSuccess invalidateQueries ['farms', farmId, 'members'] & ['farms', farmId]
```

Type: `src/features/farms/types.ts` `FarmMember extends User { role:string }` sudah ada — tambah `pivotId` atau gunakan `FarmMember.id` + lookup `farmUserId`.

### 2. UI Card Anggota

File: `src/routes/_authenticated/farms/$farmId/index.tsx`

- Header Card: `CardHeader` flex `justify-between` — tambah `Button "Tambah Manager"` (Plus icon). Visible/enabled hanya jika `currentUserRole in ['owner','manager']` (ambil dari `members` atau `useAuth` + `farm.users`).
- Dialog (`components/ui/dialog`): Input `type=email`, label "Email manager", placeholder `nama@email.com`, zod `email`, submit `addMutation.mutateAsync({farmId, email})`, loading state, tampilkan `ApiError.errors.email[0]`.
- Tabel: kolom `Nama | Email | Peran | Aksi`. Aksi = `Button Hapus` (destructive ghost) per row, disabled jika `role==='owner'` atau `row.id===currentUser.id`, onClick confirm `window.confirm` -> `removeMutation`.

Flow: buka `/farms/$farmId` -> klik Tambah -> input email terdaftar -> sukses toast "Anggota berhasil ditambahkan." -> tabel refresh.

## Validasi & Error Handling

API -> Web mapping:
- `422 exists` -> form error bawah input
- `422 already member` -> form error
- `403 authorize` (operator coba) -> toast error + tombol di web sudah hidden
- `DELETE 422 owner/self` -> toast error (frontend juga disable)
- `401` -> interceptor `src/lib/api.ts:17-20` redirect `/login`

## Pengujian

API `tests/Feature/Api/FarmApiTest.php`:
- Update `member_store_adds_member_as_manager:253` payload jadi `['email'=>$newMember->email]`.
- Baru: `member_store_fails_if_email_not_registered` -> 422, `member_store_fails_if_already_member` -> 422, `member_store_denies_operator` -> 403.
- `member_destroy` tetap: owner hapus manager OK, manager hapus manager lain OK, tidak bisa hapus owner/self.

Web: manual QA — tambah via email valid -> muncul di tabel role=manager, email tidak terdaftar -> error, hapus manager -> hilang, Staff flow tidak regresi.

## Kriteria Selesai

- Manager dapat ditambah via email terdaftar dari web Card Anggota.
- List & hapus manager berfungsi, hanya owner/manager lihat tombol.
- Error 422/403 tertampil benar.
- Staff (username) flow tidak terganggu.
