<?php

use App\Enums\EmailEventType;
use App\Models\Blast;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\EmailEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the factory persists a tenant-consistent event', function () {
    $event = EmailEvent::factory()->create();

    expect($event->exists)->toBeTrue()
        ->and($event->campaign_id)->toBe($event->blast->campaign_id)
        ->and($event->contact->campaign_id)->toBe($event->campaign_id);
});

test('an event belongs to its campaign, blast and contact', function () {
    $campaign = Campaign::factory()->create();
    $blast = Blast::factory()->for($campaign)->create();
    $contact = Contact::factory()->for($campaign)->create();
    $event = EmailEvent::factory()->create([
        'campaign_id' => $campaign->id,
        'blast_id' => $blast->id,
        'contact_id' => $contact->id,
    ]);

    expect($event->campaign->is($campaign))->toBeTrue()
        ->and($event->blast->is($blast))->toBeTrue()
        ->and($event->contact->is($contact))->toBeTrue();
});

test('the type and occurred_at columns are cast', function () {
    $event = EmailEvent::factory()->create([
        'type' => EmailEventType::Click,
        'occurred_at' => '2026-06-25 08:00:00',
    ]);

    expect($event->refresh()->type)->toBe(EmailEventType::Click)
        ->and($event->occurred_at->toDateTimeString())->toBe('2026-06-25 08:00:00');
});

test('the provider event id is unique', function () {
    EmailEvent::factory()->create(['provider_event_id' => 'evt_unique']);

    EmailEvent::factory()->create(['provider_event_id' => 'evt_unique']);
})->throws(QueryException::class);
