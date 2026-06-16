<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * Attach $user to $campaign with the given role, returning the user.
 */
function contactMember(Campaign $campaign, Role $role): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $user;
}

test('a member can list only the contacts of the route campaign', function () {
    $campaign = Campaign::factory()->create();
    $viewer = contactMember($campaign, Role::Viewer);
    Contact::factory()->for($campaign)->count(3)->create();
    Contact::factory()->for(Campaign::factory())->count(2)->create();

    $this->actingAs($viewer)
        ->get(route('campaigns.contacts.index', $campaign))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Contacts/Index')
            ->has('contacts.data', 3)
        );
});

test('a staffer can create a contact through the campaign', function () {
    $campaign = Campaign::factory()->create();
    $staffer = contactMember($campaign, Role::Staffer);

    $this->actingAs($staffer)
        ->post(route('campaigns.contacts.store', $campaign), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'phone' => '555-0100',
        ])
        ->assertRedirect(route('campaigns.contacts.index', $campaign));

    $this->assertDatabaseHas('contacts', [
        'campaign_id' => $campaign->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
});

test('the store campaign is taken from the route, never from input', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();
    $staffer = contactMember($campaign, Role::Staffer);

    $this->actingAs($staffer)
        ->post(route('campaigns.contacts.store', $campaign), [
            'campaign_id' => $other->id,
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ]);

    $contact = Contact::firstWhere('email', 'grace@example.com');

    expect($contact->campaign_id)->toBe($campaign->id);
});

test('storing a contact requires a name and a valid email', function () {
    $campaign = Campaign::factory()->create();
    $staffer = contactMember($campaign, Role::Staffer);

    $this->actingAs($staffer)
        ->from(route('campaigns.contacts.index', $campaign))
        ->post(route('campaigns.contacts.store', $campaign), [
            'name' => '',
            'email' => 'not-an-email',
        ])
        ->assertInvalid(['name', 'email']);

    expect(Contact::count())->toBe(0);
});

test('a staffer can update a contact', function () {
    $campaign = Campaign::factory()->create();
    $staffer = contactMember($campaign, Role::Staffer);
    $contact = Contact::factory()->for($campaign)->create();

    $this->actingAs($staffer)
        ->put(route('campaigns.contacts.update', [$campaign, $contact]), [
            'name' => 'Renamed Person',
            'email' => 'renamed@example.com',
            'phone' => null,
        ])
        ->assertRedirect(route('campaigns.contacts.index', $campaign));

    expect($contact->fresh()->name)->toBe('Renamed Person');
});

test('a staffer can delete a contact', function () {
    $campaign = Campaign::factory()->create();
    $staffer = contactMember($campaign, Role::Staffer);
    $contact = Contact::factory()->for($campaign)->create();

    $this->actingAs($staffer)
        ->delete(route('campaigns.contacts.destroy', [$campaign, $contact]))
        ->assertRedirect(route('campaigns.contacts.index', $campaign));

    $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
});

test('a viewer may list but may not create, update or delete contacts', function () {
    $campaign = Campaign::factory()->create();
    $viewer = contactMember($campaign, Role::Viewer);
    $contact = Contact::factory()->for($campaign)->create();

    $this->actingAs($viewer)->get(route('campaigns.contacts.index', $campaign))->assertOk();

    $this->actingAs($viewer)
        ->post(route('campaigns.contacts.store', $campaign), [
            'name' => 'Nope',
            'email' => 'nope@example.com',
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->put(route('campaigns.contacts.update', [$campaign, $contact]), [
            'name' => 'Nope',
            'email' => 'nope@example.com',
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->delete(route('campaigns.contacts.destroy', [$campaign, $contact]))
        ->assertForbidden();
});

test('a non-member is denied the contact index', function () {
    $campaign = Campaign::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('campaigns.contacts.index', $campaign))
        ->assertForbidden();
});

test('a contact from another campaign cannot be updated through this campaign', function () {
    $campaign = Campaign::factory()->create();
    $staffer = contactMember($campaign, Role::Staffer);
    $foreign = Contact::factory()->for(Campaign::factory())->create();

    $this->actingAs($staffer)
        ->put(route('campaigns.contacts.update', [$campaign, $foreign]), [
            'name' => 'Hijacked',
            'email' => 'hijack@example.com',
        ])
        ->assertNotFound();
});

test('the index search filters by name or email', function () {
    $campaign = Campaign::factory()->create();
    $staffer = contactMember($campaign, Role::Staffer);
    Contact::factory()->for($campaign)->create(['name' => 'Alice Anderson', 'email' => 'alice@example.com']);
    Contact::factory()->for($campaign)->create(['name' => 'Bob Brown', 'email' => 'bob@example.com']);

    $this->actingAs($staffer)
        ->get(route('campaigns.contacts.index', [$campaign, 'search' => 'alice']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('contacts.data', 1)
            ->where('contacts.data.0.name', 'Alice Anderson')
        );
});

test('the index sort is restricted to a whitelist', function () {
    $campaign = Campaign::factory()->create();
    $staffer = contactMember($campaign, Role::Staffer);
    Contact::factory()->for($campaign)->create(['name' => 'Bravo']);
    Contact::factory()->for($campaign)->create(['name' => 'Alpha']);

    // A whitelisted column sorts as asked.
    $this->actingAs($staffer)
        ->get(route('campaigns.contacts.index', [$campaign, 'sort' => 'name', 'direction' => 'asc']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('contacts.data.0.name', 'Alpha')
            ->where('filters.sort', 'name')
        );

    // An unknown column is ignored (no SQL injection of a raw column) and falls back to name.
    $this->actingAs($staffer)
        ->get(route('campaigns.contacts.index', [$campaign, 'sort' => 'email; drop table contacts']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.sort', 'name'));
});
