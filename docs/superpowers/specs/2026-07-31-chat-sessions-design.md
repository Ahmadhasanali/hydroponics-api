# Chat Sessions di Database — Design

Tanggal: 2026-07-31
Status: Disetujui (brainstorming)

## Tujuan

Riwayat chat Agro Bot saat ini hanya tersimpan di `localStorage` per browser. Fitur ini memindahkan penyimpanan ke database dengan model **banyak sesi bernama** (ChatGPT-style): pengguna dapat membuat, mengganti, mengganti nama, dan menghapus sesi. Server menjadi sumber kebenaran (source of truth) — klien tidak lagi mengirim `history` ke Gemini; konteks dibangun server-side dari database.

## Keputusan yang sudah disepakati

- Multiple named sessions dengan UI di widget chat
- Sesi dibuat otomatis pada pesan pertama; judul diambil dari pesan pertama (maks 60 karakter), bisa diubah (rename)
- Rename ✓, Delete ✓ (soft delete, dipurge permanen setelah 24 jam via scheduler), batas 50 sesi/pengguna (tertua di-prune)
- Migrasi satu kali riwayat localStorage → satu sesi di DB; setelahnya localStorage diabaikan

## Data Model

### Tabel `chat_sessions`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users | indexed |
| `title` | string nullable | null = "Sesi baru"; auto dari pesan pertama (maks 60) |
| `deleted_at` | timestamp nullable | soft delete |
| `created_at` / `updated_at` | timestamps | |

Index: `(user_id, updated_at)`.

### Tabel `chat_messages`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `chat_session_id` | FK chat_sessions, cascade | indexed |
| `role` | enum-ish string | `user` \| `assistant` |
| `content` | text | |
| `created_at` | timestamp | |

Hanya pesan tampil (user & assistant final) yang disimpan. Pesan tool-call internal tidak pernah disimpan.

### Model & Factory

- `App\Models\Chat\ChatSession` — `HasMany` ChatMessage, `BelongsTo` User, soft deletes
- `App\Models\Chat\ChatMessage` — `BelongsTo` ChatSession
- Factory untuk keduanya (konvensi repo)

## API

Semua di grup `auth` + `throttle:10,1`, selalu diskope ke `user_id` pemilik (bukan milik sendiri → 404).

| Method | Path | Fungsi |
|---|---|---|
| GET | `/api/chat/sessions` | Daftar sesi (soft-deleted dikecualikan), urut `updated_at` desc, maks 50, `withCount('messages')` |
| POST | `/api/chat/sessions` | Buat sesi kosong (`title` null) |
| PATCH | `/api/chat/sessions/{session}` | Rename (`title` required, 1–60) |
| DELETE | `/api/chat/sessions/{session}` | Soft delete |
| GET | `/api/chat/sessions/{session}/messages` | Muat thread |
| POST | `/api/chat/sessions/migrate` | Import satu kali dari localStorage |
| POST | `/api/chat` | Ubah kontrak: `{session_id?, message}` → `{session_id, title?, reply}` |

### POST /api/chat (perubahan)

- Validasi: `session_id` nullable integer, `message` required string maks 2000
- `session_id` tidak ada → buat `ChatSession` baru
- Muat ≤20 pesan terakhir sesi dari DB → bangun konteks Gemini (sistem message di-prepend `GeminiService`)
- Jalankan loop tool (tidak berubah, `MAX_TOOL_ROUNDS = 4`)
- Sukses → simpan `{user, message}` dan `{assistant, reply}`; return `{session_id, title (jika baru), reply}`
- Gagal (exception) → jangan simpan apa pun; tetap 503 seperti sekarang

## Frontend (chat-widget.blade.php + chat.js)

- Header widget: tombol sesi (ikon history) → panel samping di dalam widget
  - Daftar sesi: judul + waktu relatif, sesi aktif di-highlight
  - Tombol **+ New Chat** (POST `/api/chat/sessions`)
  - Per sesi: pensil (rename inline, Enter → PATCH) + tempat sampah (konfirmasi, lalu DELETE)
- Klik sesi lain → GET messages → render thread
- Pesan pertama di sesi kosong → judul terisi (response membawa `title`)
- Tombol "Bersihkan chat" → hapus **semua pesan sesi aktif di DB** (sesi & judul tetap ada)
- Migrasi satu kali: saat init, jika `localStorage` punya chat DAN daftar sesi kosong → POST migrate, lalu `localStorage.removeItem`. Setelahnya localStorage tidak dipakai lagi
- Welcome bubble tetap lokal (tidak dipersist)
- Tidak ada `history` yang dikirim klien lagi; konteks Gemini dari DB

## Edge Cases & Error Handling

- Sesi kosong: tetap tampil di daftar, bisa dihapus, tidak pernah dikirim ke Gemini
- Sesi milik pengguna lain: semua endpoint scope `user_id` → 404
- Migrasi idempotent: hanya berjalan jika pengguna punya 0 sesi; import memvalidasi `role`, konten ≤8000, maks 20 pesan terakhir (mengikuti batas localStorage lama)
- Rate limit: `throttle:10,1` untuk seluruh grup
- Purge: command juga menghapus pesan (cascade); list endpoint `whereNull('deleted_at')`

## Scheduler & Purge

- Command `chat:purge-deleted-sessions` (artisan): hard-delete sesi dengan `deleted_at < now()-24jam` beserta pesannya
- Dijadwalkan per jam (Laravel scheduler)
- Prune limit 50: saat sesi ke-51 dibuat → soft-delete sesi tertua berdasarkan `updated_at` (mengikuti jalur purge yang sama)

## Testing

PHPUnit feature (pola `tests/Feature/Chat/ChatTest.php` + `Http::fake`):

1. POST /api/chat tanpa `session_id` → auto-create, pesan user+assistant tersimpan
2. POST /api/chat dengan `session_id` → konteks dari DB (bukan klien)
3. Daftar sesi mengecualikan soft-deleted, urut `updated_at`
4. Rename PATCH: validasi 1–60, owner-only
5. Soft delete → hilang dari daftar; command purge menghapus permanen setelah 24 jam
6. Migrate: import + klaim sekali; no-op saat sesi sudah ada
7. Sesi ke-51 → prune tertua
8. Guest → 401 semua endpoint
9. Regression: suite lengkap tetap hijau

## Verifikasi

- `vendor/bin/sail artisan test --compact` (seluruh suite)
- `vendor/bin/sail bin pint --format agent`
- Browser walkthrough live di :8082: buat sesi → chat → rename → buat sesi lain → pindah sesi → hapus → purge
