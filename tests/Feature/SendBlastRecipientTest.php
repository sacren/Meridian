<?php

use App\Enums\BlastStatus;
use App\Enums\DeliveryStatus;
use App\Jobs\SendBlastRecipient;
use App\Mail\EmailProvider;
use App\Mail\FakeEmailProvider;
use App\Models\Blast;
use App\Models\BlastRecipient;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Exceptions;

/**
 * A blast mid-send, with the given number of pending recipients.
 *
 * @return array{0: Blast, 1: Collection<int, BlastRecipient>}
 */
function sendBlastRecipientSendingBlast(int $recipients = 1): array
{
    $blast = Blast::factory()->create(['status' => BlastStatus::Sending]);
    $rows = BlastRecipient::factory()->count($recipients)->create(['blast_id' => $blast->id]);

    return [$blast, $rows];
}

/**
 * A provider that always rejects the send, standing in for an outage.
 */
function sendBlastRecipientFailingProvider(): EmailProvider
{
    return new class implements EmailProvider
    {
        public function send(string $to, Mailable $mailable): string
        {
            throw new RuntimeException('provider unavailable');
        }
    };
}

test('the job sends through the provider and records the message id', function () {
    [, $rows] = sendBlastRecipientSendingBlast();
    $recipient = $rows->first();
    $fake = new FakeEmailProvider;

    (new SendBlastRecipient($recipient))->handle($fake);

    expect($fake->hasSentTo($recipient->contact->email))->toBeTrue();

    $recipient->refresh();
    expect($recipient->status)->toBe(DeliveryStatus::Sent);
    expect($recipient->provider_message_id)->toBe($fake->sent[0]['message_id']);
    expect($recipient->sent_at)->not->toBeNull();
});

test('the blast settles to sent only once the last recipient resolves', function () {
    [$blast, $rows] = sendBlastRecipientSendingBlast(2);
    $fake = new FakeEmailProvider;

    (new SendBlastRecipient($rows[0]))->handle($fake);
    expect($blast->refresh()->status)->toBe(BlastStatus::Sending);

    (new SendBlastRecipient($rows[1]))->handle($fake);
    expect($blast->refresh()->status)->toBe(BlastStatus::Sent);
});

test('a provider error fails the delivery and is reported', function () {
    Exceptions::fake();
    [$blast, $rows] = sendBlastRecipientSendingBlast();
    $recipient = $rows->first();

    (new SendBlastRecipient($recipient))->handle(sendBlastRecipientFailingProvider());

    $recipient->refresh();
    expect($recipient->status)->toBe(DeliveryStatus::Failed);
    expect($recipient->failed_at)->not->toBeNull();
    expect($recipient->provider_message_id)->toBeNull();
    expect($blast->refresh()->status)->toBe(BlastStatus::Failed);
    Exceptions::assertReported(RuntimeException::class);
});

test('a partly failed fan-out still settles the blast to sent', function () {
    Exceptions::fake();
    [$blast, $rows] = sendBlastRecipientSendingBlast(2);
    $fake = new FakeEmailProvider;

    (new SendBlastRecipient($rows[0]))->handle($fake);
    (new SendBlastRecipient($rows[1]))->handle(sendBlastRecipientFailingProvider());

    expect($blast->refresh()->status)->toBe(BlastStatus::Sent);
    expect($rows[0]->refresh()->status)->toBe(DeliveryStatus::Sent);
    expect($rows[1]->refresh()->status)->toBe(DeliveryStatus::Failed);
});
