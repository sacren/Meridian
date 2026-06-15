<?php

use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the factory persists a contact tied to a campaign', function () {
    $contact = Contact::factory()->create();

    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'campaign_id' => $contact->campaign_id,
        'name' => $contact->name,
        'email' => $contact->email,
        'phone' => $contact->phone,
    ]);

    expect($contact->campaign_id)->not->toBeNull();
});

test('a contact belongs to its campaign and the campaign has many contacts', function () {
    $campaign = Campaign::factory()->create();
    $contact = Contact::factory()->for($campaign)->create();

    expect($contact->campaign)->toBeInstanceOf(Campaign::class)
        ->and($contact->campaign->is($campaign))->toBeTrue()
        ->and($campaign->contacts)->toHaveCount(1)
        ->and($campaign->contacts->first()->is($contact))->toBeTrue();
});

test('custom_fields is cast to an array', function () {
    $contact = Contact::factory()->create([
        'custom_fields' => ['source' => 'import', 'vip' => true],
    ]);

    expect($contact->fresh()->custom_fields)
        ->toBeArray()
        ->toEqualCanonicalizing(['source' => 'import', 'vip' => true]);
});

test('only name, email, phone and custom_fields are mass assignable', function () {
    expect((new Contact)->getFillable())->toBe(['name', 'email', 'phone', 'custom_fields']);
});
