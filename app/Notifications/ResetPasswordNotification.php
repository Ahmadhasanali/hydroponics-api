<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    use Queueable;

    /**
     * Get the reset password notification mail representation.
     */
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('Reset Kata Sandi Anda')
            ->view('email.reset-password', [
                'url' => $url,
                'name' => $notifiable->name,
                'expire' => config('auth.passwords.users.expire', 60),
            ]);
    }
}
