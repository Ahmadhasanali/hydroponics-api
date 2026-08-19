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
            ->subject(__('Reset Kata Sandi Anda'))
            ->greeting(__('Halo :name!', ['name' => $notifiable->name]))
            ->line(__('Anda menerima email ini karena kami menerima permintaan reset kata sandi untuk akun Anda.'))
            ->action(__('Reset Kata Sandi'), $url)
            ->line(__('Tautan ini berlaku selama :count menit.', ['count' => config('auth.passwords.users.expire', 60)]))
            ->line(__('Jika Anda tidak meminta reset kata sandi, abaikan email ini.'));
    }

    /**
     * Get the reset URL for the given notifiable.
     *
     * Points at the SPA frontend reset page; the SPA reads the token and email
     * from the query string. The legacy web route this used to target was
     * removed when the app was converted to API-only.
     */
    protected function resetUrl($notifiable): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');

        return $frontendUrl.'/reset-password?token='.$this->token.'&email='.$notifiable->getEmailForPasswordReset();
    }
}