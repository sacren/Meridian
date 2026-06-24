<?php

namespace App\Mail;

use App\Models\Blast;
use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One blast addressed to one contact. The send pipeline builds a fresh mailable
 * per recipient so each delivery carries that contact's context; subject and body
 * come straight from the blast being sent.
 */
class BlastMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Blast $blast,
        public Contact $contact,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->blast->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.blast',
        );
    }
}
