# Desain: Chatbot AI Agrikultur (Agro Bot) dengan Google Gemini API

**Tanggal:** 2026-07-31
**Status:** Disetujui

## Ringkasan

Menambahkan chatbot AI ke Hydroponic Farm Management System yang dapat:
1. Berdiskusi umum tentang agrikultur, terutama budidaya selada (hidroponik NFT).
2. Membaca data farm milik pengguna (tank, PPM, pH, riwayat monitoring, nutrisi, pH Down).

Menggunakan **Google Gemini API** (free tier: Gemini 1.5 Flash / 2.0 Flash, 60 req/menit, 1500 req/hari) dengan **Gemini Function Calling** untuk akses data farm. Tidak menggunakan LLM lokal karena keterbatasan spesifikasi perangkat.

## Keputusan Kunci

- **Provider:** Google Gemini API (gratis, dukungan Bahasa Indonesia baik).
- **Pola interaksi:** Floating chat widget di semua halaman (user yang login), bukan halaman terpisah.
- **Akses data:** Gemini Function Calling — Gemini memanggil "tools" PHP untuk query data farm secara real-time.
- **Extensibility:** Setiap tool adalah class terpisah yang terdeteksi otomatis oleh registry (auto-discovery), sehingga menambah tool baru tidak mengubah kode lain.

## Arsitektur & Komponen

### File Baru

| File | Fungsi |
|------|--------|
| `config/gemini.php` | Konfigurasi: API key, model, max tokens, system prompt |
| `app/Services/GeminiService.php` | HTTP client ke Gemini API: kirim pesan + history + tool declarations, proses respons |
| `app/Services/ChatToolsService.php` | Registry tools: auto-discovery class tool, generate function declarations, dispatch panggilan |
| `app/ChatTools/ChatToolContract.php` | Kontrak tool (nama, deskripsi, parameter schema, handler) |
| `app/ChatTools/*Tool.php` | 6 class tool (daftar di bawah) |
| `app/Http/Controllers/ChatController.php` | Endpoint `POST /api/chat` — orchestrate Gemini + tools |
| `resources/views/partials/chat-widget.blade.php` | Floating tombol + modal chat |
| `resources/js/chat.js` | Vanilla JS: toggle modal, kirim pesan, render bubble, localStorage |
| `routes/chat.php` | Route chat endpoint |

### Alur

```
User ketik → chat.js → POST /api/chat → ChatController
    → GeminiService.sendMessage(pesan + history + tool_declarations)
    → Gemini jawab (teks biasa) ATAU (functionCall)
    → Jika functionCall → ChatToolsService->handle(nama, args) → query DB (dengan guard kepemilikan) → kirim hasil ke Gemini → Gemini rangkai jawaban akhir
    → Kembalikan jawaban ke frontend
```

Untuk pertanyaan umum agrikultur/selada, Gemini menjawab langsung dari pengetahuannya. Untuk pertanyaan data spesifik, Gemini memanggil tool yang tersedia.

## UI/UX Chat Widget

Mengikuti gaya existing aplikasi (rounded-[2rem], warna aksen `#ffce54`, font Plus Jakarta Sans, Bootstrap Icons).

1. **Floating button** — pojok kanan bawah, ikon `bi-chat-dots`, 56px, hanya tampil untuk user login (di dalam `layouts/app.blade.php`).
2. **Modal chat** — panel 380×560px; mobile: full-width bottom sheet:
   - **Header**: avatar bot + judul "Agro Bot" + subtitle "Asisten Agrikultur"
   - **Message area**: bubble user (kanan, `#ffce54`), bubble bot (kiri, putih)
   - **Input area**: textarea + tombol kirim, disable saat loading, tombol clear chat
3. **Interaksi (vanilla JS di `chat.js`)**:
   - Toggle open/close dengan animasi
   - Kirim via `fetch` ke `/api/chat`, pending state "mengetik..."
   - Riwayat chat di **localStorage** (key `agrobot_chats_{user_id}`), dimuat saat modal dibuka
   - Enter = kirim, Shift+Enter = baris baru

## Tools (Function Calling)

### Daftar Tools (v1)

| Tool | Fungsi | Parameter |
|------|--------|-----------|
| `get_farms` | Daftar farm milik user | — |
| `get_tanks` | Daftar tank (opsional filter farm) | `farm_id?` |
| `get_tank_status` | Status terkini PPM/pH/suhu satu tank | `tank_id` |
| `get_monitoring_history` | Riwayat monitoring per tank, opsional rentang hari | `tank_id`, `days?` (default 7) |
| `get_nutrient_history` | Riwayat penambahan AB Mix per tank | `tank_id`, `days?` |
| `get_ph_down_history` | Riwayat pH Down per tank | `tank_id`, `days?` |

### Struktur Extensible

- `app/ChatTools/ChatToolContract.php` — kontrak: nama, deskripsi, parameter schema (format Gemini), handler `handle(array $args, User $user): array`.
- Setiap tool = 1 class di `app/ChatTools/`.
- `ChatToolsService` melakukan auto-discovery class dari direktori `app/ChatTools/`, menghasilkan `function_declarations` untuk Gemini, dan mendispatch panggilan ke handler yang tepat.
- **Menambah tool baru:** buat class baru yang implement kontrak — otomatis terdeteksi, tanpa mengubah kode lain.

### Guard Kepemilikan

Setiap handler tool wajib memverifikasi bahwa data farm yang diakses adalah farm di mana user terdaftar sebagai member (`farm_users`). Data farm user lain tidak boleh bocor.

## System Prompt

Disimpan di `config/gemini.php`. Isi:
- Identitas: "Agro Bot", asisten agrikultur & hidroponik, fokus khusus selada
- Selalu jawab dalam Bahasa Indonesia
- Pertanyaan umum → jawab dari pengetahuan; pertanyaan data → panggil tool dulu, jangan menebak
- Jangan pernah menyebut angka yang tidak ada di data

## Error Handling

- Error Gemini API (rate limit, invalid key) → jawaban fallback ramah: "Maaf, layanan AI sedang sibuk. Coba lagi sebentar."
- Error tool handler (tank tidak ditemukan, akses ditolak) → kirim hasil error ke Gemini agar merangkai jawaban jujur, bukan crash
- Timeout request → retry 1x, lalu fallback
- **Rate limiting per user:** 10 pesan/menit (middleware `throttle`)

## Security

- Semua endpoint di balik middleware `auth`
- Guard kepemilikan data di tiap tool handler
- API key Gemini hanya di `.env`/`config`, tidak pernah di frontend
- Hanya hasil query tool (data farm milik user) yang dikirim ke Gemini

## Testing (PHPUnit)

- Endpoint chat: sukses kirim pesan → balasan Gemini (di-mock)
- Function calling: mock Gemini balas `functionCall` → handler terpanggil → hasil dikembalikan → jawaban final
- Authorization: user A tidak bisa akses data farm user B
- Rate limit: request ke-11 dalam 1 menit → 429
- Unit test tiap tool handler (query benar, format data benar)

## Konfigurasi `.env`

```
GEMINI_API_KEY=
GEMINI_MODEL=gemini-1.5-flash
```
