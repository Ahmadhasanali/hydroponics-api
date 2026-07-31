<?php

namespace App\ChatTools;

use App\Models\User;

interface ChatToolContract
{
    public function name(): string;

    public function description(): string;

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * @param  array<string, mixed>  $args
     * @return array{data: mixed}|array{error: string}
     */
    public function handle(array $args, User $user): array;
}
