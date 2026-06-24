<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Str;

/**
 * A recording stand-in for {@see EmailProvider} used in tests. Rather than touch
 * a mailer, it mints a synthetic message id and remembers every send so a test
 * can assert that a given recipient received a given subject. Bind it in place of
 * the real provider via the container to capture a send pipeline's fan-out.
 */
class FakeEmailProvider implements EmailProvider
{
    /**
     * Every message handed to the provider, in send order.
     *
     * @var list<array{to: string, subject: string, message_id: string}>
     */
    public array $sent = [];

    public function send(string $to, Mailable $mailable): string
    {
        $messageId = (string) Str::uuid();

        $this->sent[] = [
            'to' => $to,
            'subject' => (string) $mailable->envelope()->subject,
            'message_id' => $messageId,
        ];

        return $messageId;
    }

    /**
     * Whether a message was recorded for the given recipient.
     */
    public function hasSentTo(string $to): bool
    {
        return collect($this->sent)->contains(fn (array $message): bool => $message['to'] === $to);
    }
}
