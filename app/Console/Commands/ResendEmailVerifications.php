<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ResendEmailVerifications extends Command
{
    protected $signature = 'email:resend-unverified';

    protected $description = 'Kirim ulang email verifikasi untuk user yang belum verifikasi';

    public function handle(): int
    {
        $interval = (int) config('auth.verification.resend_interval', 5);

        $users = User::query()
            ->whereNull('email_verified_at')
            ->where(function (Builder $query) use ($interval): void {
                $query->whereNull('verification_sent_at')
                    ->where('created_at', '<=', now()->subMinutes($interval));
                $query->orWhere('verification_sent_at', '<=', now()->subMinutes($interval));
            })
            ->get();

        foreach ($users as $user) {
            $user->sendEmailVerificationNotification();
        }

        $this->info("Email verifikasi dikirim ulang ke {$users->count()} user.");

        return self::SUCCESS;
    }
}
