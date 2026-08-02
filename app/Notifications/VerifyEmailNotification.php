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
            ->subject('Verifikasi Email Anda')
            ->view('email.verify-email', [
                'url' => $url,
                'name' => $notifiable->name,
                'expire' => config('auth.verification.expire', 60),
            ]);
    }
}
