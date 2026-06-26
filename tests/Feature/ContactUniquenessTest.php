<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Attach $user to $campaign with the given role, returning the user.
 */
function contactUniquenessStaffer(Campaign $campaign): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => Role::Staffer->value]);

    return $user;
}

test('a duplicate email in the same campaign is rejected on create', function () {
    $campaign = Campaign::factory()->create();
    $staffer = contactUniquenessStaffer($campaign);
    Contact::factory()->for($campaign)->create(['email' => 'ada@example.com']);

    $this->actingAs($staffer)
        ->from(route('campaigns.contacts.index', $campaign))
        ->post(route('campaigns.contacts.store', $campaign), [
            'name' => 'Ada Again',
            'email' => 'ada@example.com',
        ])
        ->assertInvalid(['email']);

    expect($campaign->contacts()->count())->toBe(1);
});

test('updating a contact into a colliding email is rejected', function () {
    $campaign = Campaign::factory()->create();
    $staffer = contactUniquenessStaffer($campaign);
    Contact::factory()->for($campaign)->create(['email' => 'taken@example.com']);
    $contact = Contact::factory()->for($campaign)->create(['email' => 'free@example.com']);

    $this->actingAs($staffer)
        ->from(route('campaigns.contacts.index', $campaign))
        ->put(route('campaigns.contacts.update', [$campaign, $contact]), [
            'name' => $contact->name,
            'email' => 'taken@example.com',
        ])
        ->assertInvalid(['email']);

    expect($contact->fresh()->email)->toBe('free@example.com');
});

test('the same email is allowed in a different campaign', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();
    $staffer = contactUniquenessStaffer($other);
    Contact::factory()->for($campaign)->create(['email' => 'shared@example.com']);

    $this->actingAs($staffer)
        ->post(route('campaigns.contacts.store', $other), [
            'name' => 'Shared Elsewhere',
            'email' => 'shared@example.com',
        ])
        ->assertRedirect(route('campaigns.contacts.index', $other));

    $this->assertDatabaseHas('contacts', [
        'campaign_id' => $other->id,
        'email' => 'shared@example.com',
    ]);
});

test('a mixed-case and whitespace email is stored normalized', function () {
    $campaign = Campaign::factory()->create();
    $staffer = contactUniquenessStaffer($campaign);

    $this->actingAs($staffer)
        ->post(route('campaigns.contacts.store', $campaign), [
            'name' => 'Grace Hopper',
            'email' => '  Grace@Example.COM  ',
        ])
        ->assertRedirect(route('campaigns.contacts.index', $campaign));

    $this->assertDatabaseHas('contacts', [
        'campaign_id' => $campaign->id,
        'email' => 'grace@example.com',
    ]);
});

test('normalization makes a differently-cased duplicate collide on create', function () {
    $campaign = Campaign::factory()->create();
    $staffer = contactUniquenessStaffer($campaign);
    Contact::factory()->for($campaign)->create(['email' => 'ada@example.com']);

    $this->actingAs($staffer)
        ->from(route('campaigns.contacts.index', $campaign))
        ->post(route('campaigns.contacts.store', $campaign), [
            'name' => 'Ada Uppercase',
            'email' => 'ADA@EXAMPLE.COM',
        ])
        ->assertInvalid(['email']);

    expect($campaign->contacts()->count())->toBe(1);
});

test('re-saving a contact with its own email still passes', function () {
    $campaign = Campaign::factory()->create();
    $staffer = contactUniquenessStaffer($campaign);
    $contact = Contact::factory()->for($campaign)->create(['email' => 'self@example.com']);

    $this->actingAs($staffer)
        ->put(route('campaigns.contacts.update', [$campaign, $contact]), [
            'name' => 'Renamed Self',
            'email' => 'self@example.com',
        ])
        ->assertRedirect(route('campaigns.contacts.index', $campaign));

    expect($contact->fresh())
        ->name->toBe('Renamed Self')
        ->email->toBe('self@example.com');
});
