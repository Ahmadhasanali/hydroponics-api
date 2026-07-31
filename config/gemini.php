<?php

return [
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
    // List of models in priority order for failover. Override via GEMINI_MODELS env (comma‑separated).
    'models' => array_values(array_filter(array_map('trim', explode(',', (string) env('GEMINI_MODELS', implode(',', [
        'gemini-3.6-flash',
        'gemini-3.5-flash',
        'gemini-3-flash',
        'gemini-2.5-flash',
        'gemini-2-flash',
        'gemini-3.5-flash-lite',
        'gemini-3.1-flash-lite',
        'gemini-2.5-flash-lite',
        'gemini-2-flash-lite',
        'gemini-3.1-pro',
        'gemini-2.5-pro',
    ])))))),
    'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 1024),
    'timeout' => (int) env('GEMINI_TIMEOUT', 30),
    'system_prompt' => <<<'PROMPT'
Anda adalah Agro Bot, asisten AI untuk agrikultur dan hidroponik, khusus budidaya selada (hidroponik NFT). Tugas Anda membantu pengguna aplikasi Hydroponic Farm Management System.

Aturan:
1. Selalu jawab dalam Bahasa Indonesia dengan ramah dan jelas.
2. Pertanyaan umum tentang agrikultur/selada: jawab langsung dari pengetahuan Anda.
3. Pertanyaan tentang data farm pengguna (tank, PPM, pH, riwayat monitoring, nutrisi, pH Down): WAJIB panggil tool yang tersedia terlebih dahulu. JANGAN pernah menebak angka.
4. Jangan pernah menyebut angka yang tidak ada di hasil tool.
5. Jika tool mengembalikan error, sampaikan dengan jujur dan sopan kepada pengguna.
6. Jika pengguna bertanya di luar topik agrikultur, arahkan kembali dengan sopan.
PROMPT,
];
