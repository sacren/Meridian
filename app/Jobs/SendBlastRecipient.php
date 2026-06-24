<?php

namespace App\Jobs;

use App\Enums\BlastStatus;
use App\Enums\DeliveryStatus;
use App\Mail\BlastMailable;
use App\Mail\EmailProvider;
use App\Models\BlastRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Delivers one recipient of a sending blast through the {@see EmailProvider}
 * seam. One job is dispatched per recipient, so a provider error fails only that
 * delivery (recorded on the row) rather than the whole fan-out. After each
 * delivery resolves, the job settles the blast itself once no recipient is left
 * pending: Sent if any delivery succeeded, otherwise Failed.
 */
class SendBlastRecipient implements ShouldQueue
{
    use Queueable;

    public function __construct(public BlastRecipient $recipient) {}

    public function handle(EmailProvider $provider): void
    {
        $blast = $this->recipient->blast;
        $contact = $this->recipient->contact;

        try {
            $messageId = $provider->send($contact->email, new BlastMailable($blast, $contact));

            $this->recipient->update([
                'provider_message_id' => $messageId,
                'status' => DeliveryStatus::Sent,
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);

            $this->recipient->update([
                'status' => DeliveryStatus::Failed,
                'failed_at' => now(),
            ]);
        }

        $this->settleBlast();
    }

    /**
     * Settle the blast once every recipient has left the pending state. A blast
     * counts as Sent if at least one delivery succeeded; only a wholly failed
     * fan-out settles to Failed.
     */
    protected function settleBlast(): void
    {
        $blast = $this->recipient->blast;

        if ($blast->recipients()->where('status', DeliveryStatus::Pending)->exists()) {
            return;
        }

        $anySent = $blast->recipients()->where('status', DeliveryStatus::Sent)->exists();

        $blast->update([
            'status' => $anySent ? BlastStatus::Sent : BlastStatus::Failed,
        ]);
    }
}
