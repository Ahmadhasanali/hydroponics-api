<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends BaseVerifyEmail
{
    use Queueable;

    /**
     * Get the verify email notification mail representation.
     */
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject(__('Verifikasi Email Anda'))
            ->greeting(__('Halo :name!', ['name' => $notifiable->name]))
            ->line(__('Klik tombol di bawah untuk memverifikasi alamat email Anda.'))
            ->action(__('Verifikasi Email'), $url)
            ->line(__('Tautan ini berlaku selama :count menit.', ['count' => config('auth.verification.expire', 60)]))
            ->line(__('Jika Anda tidak membuat akun ini, abaikan email ini.'));
    }
}
