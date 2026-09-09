<?php

namespace App\Notifications;

use App\Mail\VerifyEmailMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Mail\Mailable;

/**
 * Same signed-URL mechanics as Laravel's default VerifyEmail (reused as-is
 * via the parent class) — only the actual email is replaced, with a
 * branded, localized one instead of the framework's generic English one.
 */
class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): Mailable
    {
        // Unlike MailMessage, a Mailable returned here isn't automatically
        // addressed to the notifiable — MailChannel just calls ->send() on
        // it as-is, so the recipient has to be set explicitly.
        return (new VerifyEmailMail($notifiable, $this->verificationUrl($notifiable)))
            ->to((string) $notifiable->getEmailForVerification());
    }
}
