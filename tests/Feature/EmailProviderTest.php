<?php

use App\Enums\BlastStatus;
use App\Mail\BlastMailable;
use App\Mail\EmailProvider;
use App\Mail\FakeEmailProvider;
use App\Mail\MailerEmailProvider;
use App\Models\Blast;
use App\Models\Contact;
use Illuminate\Support\Facades\Mail;

/**
 * Build a blast addressed at a contact in the same campaign.
 *
 * @return array{0: Blast, 1: Contact}
 */
function emailProviderBlastForContact(string $subject = 'Spring sale'): array
{
    $blast = Blast::factory()->create(['subject' => $subject]);
    $contact = Contact::factory()->create(['campaign_id' => $blast->campaign_id]);

    return [$blast, $contact];
}

test('the container resolves the real mailer provider by default', function () {
    expect(app(EmailProvider::class))->toBeInstanceOf(MailerEmailProvider::class);
});

test('the fake provider records the send and returns a message id', function () {
    [$blast, $contact] = emailProviderBlastForContact('Spring sale');
    $fake = new FakeEmailProvider;

    $messageId = $fake->send($contact->email, new BlastMailable($blast, $contact));

    expect($messageId)->not->toBeEmpty();
    expect($fake->sent)->toHaveCount(1);
    expect($fake->sent[0])->toMatchArray([
        'to' => $contact->email,
        'subject' => 'Spring sale',
        'message_id' => $messageId,
    ]);
    expect($fake->hasSentTo($contact->email))->toBeTrue();
});

test('the mailer provider dispatches the blast through Laravel mail', function () {
    Mail::fake();

    [$blast, $contact] = emailProviderBlastForContact('Spring sale');

    app(EmailProvider::class)->send($contact->email, new BlastMailable($blast, $contact));

    Mail::assertSent(
        BlastMailable::class,
        fn (BlastMailable $mail): bool => $mail->hasTo($contact->email)
            && $mail->blast->is($blast)
            && $mail->hasSubject('Spring sale'),
    );
});

test('a blast casts the sending lifecycle states', function () {
    $blast = Blast::factory()->create(['status' => BlastStatus::Sending]);

    expect($blast->refresh()->status)->toBe(BlastStatus::Sending);
});
