<?php

namespace App\Console\Commands;

use App\Models\Chat\ChatSession;
use Illuminate\Console\Command;

class PurgeDeletedChatSessions extends Command
{
    protected $signature = 'chat:purge-deleted-sessions';

    protected $description = 'Permanently delete chat sessions trashed more than 24 hours ago';

    public function handle(): int
    {
        $cutoff = now()->subHours(24);

        ChatSession::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->get()
            ->each->forceDelete();

        return self::SUCCESS;
    }
}
