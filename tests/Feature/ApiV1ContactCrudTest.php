<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\User;

/**
 * Attach a new user to the campaign with the given role and return the user.
 */
function apiV1ContactMember(Campaign $campaign, Role $role): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $user;
}

/**
 * Mint a bearer token for the given user.
 */
function apiV1ContactToken(User $user): string
{
    return $user->createToken('contact-crud-test')->plainTextToken;
}

test('index lists only the route campaign\'s contacts in a paginated envelope', function () {
    $campaign = Campaign::factory()->create();
    $viewer = apiV1ContactMember($campaign, Role::Viewer);
    Contact::factory()->for($campaign)->count(3)->create();
    Contact::factory()->for(Campaign::factory())->count(2)->create();

    $this->withToken(apiV1ContactToken($viewer))
        ->getJson("/api/v1/campaigns/{$campaign->slug}/contacts")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'name', 'email', 'phone', 'custom_fields', 'created_at']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

test('index requires authentication', function () {
    $campaign = Campaign::factory()->create();

    $this->getJson("/api/v1/campaigns/{$campaign->slug}/contacts")
        ->assertUnauthorized();
});

test('a non-member is forbidden from the contact index', function () {
    $campaign = Campaign::factory()->create();
    $stranger = User::factory()->create();

    $this->withToken(apiV1ContactToken($stranger))
        ->getJson("/api/v1/campaigns/{$campaign->slug}/contacts")
        ->assertForbidden();
});

test('a staffer can create a contact and the campaign comes from the route', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();
    $staffer = apiV1ContactMember($campaign, Role::Staffer);

    $this->withToken(apiV1ContactToken($staffer))
        ->postJson("/api/v1/campaigns/{$campaign->slug}/contacts", [
            'campaign_id' => $other->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'phone' => '555-0100',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Ada Lovelace')
        ->assertJsonPath('data.email', 'ada@example.com');

    $contact = Contact::firstWhere('email', 'ada@example.com');

    expect($contact->campaign_id)->toBe($campaign->id);
});

test('creating a contact requires a name and a valid email', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1ContactMember($campaign, Role::Staffer);

    $this->withToken(apiV1ContactToken($staffer))
        ->postJson("/api/v1/campaigns/{$campaign->slug}/contacts", [
            'name' => '',
            'email' => 'not-an-email',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email']);

    expect(Contact::count())->toBe(0);
});

test('a staffer can update a contact', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1ContactMember($campaign, Role::Staffer);
    $contact = Contact::factory()->for($campaign)->create();

    $this->withToken(apiV1ContactToken($staffer))
        ->putJson("/api/v1/campaigns/{$campaign->slug}/contacts/{$contact->id}", [
            'name' => 'Renamed Person',
            'email' => 'renamed@example.com',
            'phone' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed Person');

    expect($contact->fresh()->name)->toBe('Renamed Person');
});

test('a staffer can delete a contact', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1ContactMember($campaign, Role::Staffer);
    $contact = Contact::factory()->for($campaign)->create();

    $this->withToken(apiV1ContactToken($staffer))
        ->deleteJson("/api/v1/campaigns/{$campaign->slug}/contacts/{$contact->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
});

test('a viewer may list but may not create, update or delete contacts', function () {
    $campaign = Campaign::factory()->create();
    $viewer = apiV1ContactMember($campaign, Role::Viewer);
    $contact = Contact::factory()->for($campaign)->create();
    $token = apiV1ContactToken($viewer);

    $this->withToken($token)
        ->getJson("/api/v1/campaigns/{$campaign->slug}/contacts")
        ->assertOk();

    $this->withToken($token)
        ->postJson("/api/v1/campaigns/{$campaign->slug}/contacts", [
            'name' => 'Nope',
            'email' => 'nope@example.com',
        ])
        ->assertForbidden();

    $this->withToken($token)
        ->putJson("/api/v1/campaigns/{$campaign->slug}/contacts/{$contact->id}", [
            'name' => 'Nope',
            'email' => 'nope@example.com',
        ])
        ->assertForbidden();

    $this->withToken($token)
        ->deleteJson("/api/v1/campaigns/{$campaign->slug}/contacts/{$contact->id}")
        ->assertForbidden();
});

test('a contact from another campaign cannot be reached through this campaign', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1ContactMember($campaign, Role::Staffer);
    $foreign = Contact::factory()->for(Campaign::factory())->create();

    $this->withToken(apiV1ContactToken($staffer))
        ->putJson("/api/v1/campaigns/{$campaign->slug}/contacts/{$foreign->id}", [
            'name' => 'Hijacked',
            'email' => 'hijack@example.com',
        ])
        ->assertNotFound();
});

test('the index search filters by name or email', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1ContactMember($campaign, Role::Staffer);
    Contact::factory()->for($campaign)->create(['name' => 'Alice Anderson', 'email' => 'alice@example.com']);
    Contact::factory()->for($campaign)->create(['name' => 'Bob Brown', 'email' => 'bob@example.com']);

    $this->withToken(apiV1ContactToken($staffer))
        ->getJson("/api/v1/campaigns/{$campaign->slug}/contacts?search=alice")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Alice Anderson');
});

test('the index sort is restricted to a whitelist', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1ContactMember($campaign, Role::Staffer);
    Contact::factory()->for($campaign)->create(['name' => 'Bravo']);
    Contact::factory()->for($campaign)->create(['name' => 'Alpha']);
    $token = apiV1ContactToken($staffer);

    // A whitelisted column sorts as asked.
    $this->withToken($token)
        ->getJson("/api/v1/campaigns/{$campaign->slug}/contacts?sort=name&direction=asc")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Alpha');

    // An unknown column is ignored (no raw column injected) and falls back to name.
    $this->withToken($token)
        ->getJson("/api/v1/campaigns/{$campaign->slug}/contacts?sort=email;+drop+table+contacts")
        ->assertOk();
});
