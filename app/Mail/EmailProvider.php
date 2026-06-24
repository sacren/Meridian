<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

/**
 * The seam through which the application hands a message off to an email
 * provider. Keeping this an interface lets the send pipeline stay provider
 * agnostic: the real binding dispatches through Laravel's Mailer (caught by
 * Mailpit locally), while a recording fake stands in for tests. Swapping in a
 * first-party transport (Postmark, Mailgun, SES) later is a config-only change
 * behind this same contract.
 */
interface EmailProvider
{
    /**
     * Deliver the message to a single recipient and return the provider's
     * message id, the key a later delivery record correlates inbound events to.
     */
    public function send(string $to, Mailable $mailable): string;
}
