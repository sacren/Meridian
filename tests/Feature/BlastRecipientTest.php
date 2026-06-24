<?php

use App\Enums\DeliveryStatus;
use App\Models\Blast;
use App\Models\BlastRecipient;
use App\Models\Contact;
use Illuminate\Database\QueryException;

test('the factory persists a pending delivery', function () {
    $recipient = BlastRecipient::factory()->create();

    expect($recipient->exists)->toBeTrue();
    expect($recipient->status)->toBe(DeliveryStatus::Pending);
    expect($recipient->provider_message_id)->toBeNull();
});

test('a delivery belongs to its blast and contact', function () {
    $recipient = BlastRecipient::factory()->create();

    expect($recipient->blast)->toBeInstanceOf(Blast::class);
    expect($recipient->contact)->toBeInstanceOf(Contact::class);
});

test('a blast has many recipients', function () {
    $blast = Blast::factory()->create();
    BlastRecipient::factory()->count(3)->create(['blast_id' => $blast->id]);

    expect($blast->recipients)->toHaveCount(3);
    expect($blast->recipients->first())->toBeInstanceOf(BlastRecipient::class);
});

test('the status column casts to the delivery status enum', function () {
    $recipient = BlastRecipient::factory()->sent()->create();

    expect($recipient->refresh()->status)->toBe(DeliveryStatus::Sent);
    expect($recipient->provider_message_id)->not->toBeNull();
    expect($recipient->sent_at)->not->toBeNull();
});

test('the recipient shares its blast campaign for tenant consistency', function () {
    $recipient = BlastRecipient::factory()->create();

    expect($recipient->contact->campaign_id)->toBe($recipient->blast->campaign_id);
});

test('a recipient appears at most once per blast', function () {
    $blast = Blast::factory()->create();
    $contact = Contact::factory()->create(['campaign_id' => $blast->campaign_id]);

    BlastRecipient::factory()->create(['blast_id' => $blast->id, 'contact_id' => $contact->id]);

    expect(fn () => BlastRecipient::factory()->create([
        'blast_id' => $blast->id,
        'contact_id' => $contact->id,
    ]))->toThrow(QueryException::class);
});
