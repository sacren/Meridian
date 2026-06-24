<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * The real email provider: it sends through Laravel's Mailer, which the Sail
 * stack routes to Mailpit locally (caught, never delivered). The provider's
 * message id is read back from the sent message so the delivery record can later
 * correlate inbound webhook events to it.
 */
class MailerEmailProvider implements EmailProvider
{
    public function send(string $to, Mailable $mailable): string
    {
        return (string) Mail::to($to)->send($mailable)?->getMessageId();
    }
}
