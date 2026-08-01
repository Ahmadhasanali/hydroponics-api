<?php

namespace App\ChatTools;

use App\Models\User;

interface ChatToolContract
{
    public function name(): string;

    public function description(): string;

    /**
     * Skema parameter sesuai format Gemini function declaration.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * Jalankan tool. Kembalikan ['data' => ...] saat sukses
     * atau ['error' => 'pesan'] saat gagal/tidak berhak.
     *
     * @param  array<string, mixed>  $args
     * @return array{data: mixed}|array{error: string}
     */
    public function handle(array $args, User $user): array;
}
