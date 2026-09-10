<?php

namespace App\Notifications;

use App\Mail\ResetPasswordMail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Mail\Mailable;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): Mailable
    {
        return (new ResetPasswordMail($notifiable, $this->resetUrl($notifiable)))
            ->to((string) $notifiable->getEmailForPasswordReset());
    }

    /**
     * The default builds a backend route URL ("password.reset") that
     * doesn't exist here — this is an API-only backend behind a React SPA,
     * so the link has to point at the frontend's own reset page instead.
     */
    protected function resetUrl($notifiable): string
    {
        $email = urlencode((string) $notifiable->getEmailForPasswordReset());

        return config('app.frontend_url')."/reset-password?token={$this->token}&email={$email}";
    }
}
