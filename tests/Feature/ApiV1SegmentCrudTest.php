<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Segment;
use App\Models\User;

/**
 * Attach a new user to the campaign with the given role and return the user.
 */
function apiV1SegmentMember(Campaign $campaign, Role $role): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $user;
}

/**
 * Mint a bearer token for the given user.
 */
function apiV1SegmentToken(User $user): string
{
    return $user->createToken('segment-crud-test')->plainTextToken;
}

/**
 * A valid criteria payload matching contacts whose email contains the needle.
 *
 * @return array{combinator: string, rules: array<int, array<string, string>>}
 */
function apiV1SegmentEmailContains(string $needle): array
{
    return [
        'combinator' => 'and',
        'rules' => [
            ['field' => 'email', 'operator' => 'contains', 'value' => $needle],
        ],
    ];
}

test('index lists only the route campaign\'s segments in a paginated envelope', function () {
    $campaign = Campaign::factory()->create();
    $viewer = apiV1SegmentMember($campaign, Role::Viewer);
    Segment::factory()->for($campaign)->count(2)->create();
    Segment::factory()->for(Campaign::factory())->count(3)->create();

    $this->withToken(apiV1SegmentToken($viewer))
        ->getJson("/api/v1/campaigns/{$campaign->slug}/segments")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'name', 'criteria', 'created_at']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

test('index requires authentication', function () {
    $campaign = Campaign::factory()->create();

    $this->getJson("/api/v1/campaigns/{$campaign->slug}/segments")
        ->assertUnauthorized();
});

test('a non-member is forbidden from the segment index', function () {
    $campaign = Campaign::factory()->create();
    $stranger = User::factory()->create();

    $this->withToken(apiV1SegmentToken($stranger))
        ->getJson("/api/v1/campaigns/{$campaign->slug}/segments")
        ->assertForbidden();
});

test('a staffer can create a segment and the campaign comes from the route', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();
    $staffer = apiV1SegmentMember($campaign, Role::Staffer);

    $this->withToken(apiV1SegmentToken($staffer))
        ->postJson("/api/v1/campaigns/{$campaign->slug}/segments", [
            'campaign_id' => $other->id,
            'name' => 'Gmail users',
            'criteria' => apiV1SegmentEmailContains('@gmail.com'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Gmail users')
        ->assertJsonPath('data.criteria.rules.0.operator', 'contains');

    $segment = Segment::firstWhere('name', 'Gmail users');

    expect($segment->campaign_id)->toBe($campaign->id);
});

test('a segment may be created with no criteria', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1SegmentMember($campaign, Role::Staffer);

    $this->withToken(apiV1SegmentToken($staffer))
        ->postJson("/api/v1/campaigns/{$campaign->slug}/segments", ['name' => 'Everyone'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Everyone');
});

test('creating a segment requires a name', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1SegmentMember($campaign, Role::Staffer);

    $this->withToken(apiV1SegmentToken($staffer))
        ->postJson("/api/v1/campaigns/{$campaign->slug}/segments", ['name' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    expect(Segment::count())->toBe(0);
});

test('criteria off the schema is rejected', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1SegmentMember($campaign, Role::Staffer);
    $token = apiV1SegmentToken($staffer);
    $url = "/api/v1/campaigns/{$campaign->slug}/segments";

    // Field outside the whitelist.
    $this->withToken($token)->postJson($url, [
        'name' => 'Bad field',
        'criteria' => ['rules' => [['field' => 'id', 'operator' => 'equals', 'value' => '1']]],
    ])->assertStatus(422)->assertJsonValidationErrors(['criteria.rules.0.field']);

    // Unknown operator.
    $this->withToken($token)->postJson($url, [
        'name' => 'Bad operator',
        'criteria' => ['rules' => [['field' => 'name', 'operator' => 'regex', 'value' => 'x']]],
    ])->assertStatus(422)->assertJsonValidationErrors(['criteria.rules.0.operator']);

    // Bad combinator.
    $this->withToken($token)->postJson($url, [
        'name' => 'Bad combinator',
        'criteria' => ['combinator' => 'xor', 'rules' => []],
    ])->assertStatus(422)->assertJsonValidationErrors(['criteria.combinator']);

    expect(Segment::count())->toBe(0);
});

test('a staffer can update a segment', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1SegmentMember($campaign, Role::Staffer);
    $segment = Segment::factory()->for($campaign)->create();

    $this->withToken(apiV1SegmentToken($staffer))
        ->putJson("/api/v1/campaigns/{$campaign->slug}/segments/{$segment->id}", [
            'name' => 'Renamed segment',
            'criteria' => apiV1SegmentEmailContains('@example.org'),
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed segment');

    expect($segment->fresh()->name)->toBe('Renamed segment');
});

test('a staffer can delete a segment', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1SegmentMember($campaign, Role::Staffer);
    $segment = Segment::factory()->for($campaign)->create();

    $this->withToken(apiV1SegmentToken($staffer))
        ->deleteJson("/api/v1/campaigns/{$campaign->slug}/segments/{$segment->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('segments', ['id' => $segment->id]);
});

test('a viewer may list and preview but may not create, update or delete segments', function () {
    $campaign = Campaign::factory()->create();
    $viewer = apiV1SegmentMember($campaign, Role::Viewer);
    $segment = Segment::factory()->for($campaign)->create();
    $token = apiV1SegmentToken($viewer);

    $this->withToken($token)
        ->getJson("/api/v1/campaigns/{$campaign->slug}/segments")
        ->assertOk();

    $this->withToken($token)
        ->postJson("/api/v1/campaigns/{$campaign->slug}/segments/preview", [
            'criteria' => apiV1SegmentEmailContains('@gmail.com'),
        ])
        ->assertOk();

    $this->withToken($token)
        ->postJson("/api/v1/campaigns/{$campaign->slug}/segments", ['name' => 'Nope'])
        ->assertForbidden();

    $this->withToken($token)
        ->putJson("/api/v1/campaigns/{$campaign->slug}/segments/{$segment->id}", ['name' => 'Nope'])
        ->assertForbidden();

    $this->withToken($token)
        ->deleteJson("/api/v1/campaigns/{$campaign->slug}/segments/{$segment->id}")
        ->assertForbidden();
});

test('a segment from another campaign cannot be reached through this campaign', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1SegmentMember($campaign, Role::Staffer);
    $foreign = Segment::factory()->for(Campaign::factory())->create();

    $this->withToken(apiV1SegmentToken($staffer))
        ->putJson("/api/v1/campaigns/{$campaign->slug}/segments/{$foreign->id}", ['name' => 'Hijacked'])
        ->assertNotFound();
});

test('preview returns the count and matched contacts for the posted criteria', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1SegmentMember($campaign, Role::Staffer);
    Contact::factory()->for($campaign)->create(['name' => 'Ada', 'email' => 'ada@gmail.com']);
    Contact::factory()->for($campaign)->create(['name' => 'Bob', 'email' => 'bob@yahoo.com']);
    Contact::factory()->for($campaign)->create(['name' => 'Cy', 'email' => 'cy@gmail.com']);

    $this->withToken(apiV1SegmentToken($staffer))
        ->postJson("/api/v1/campaigns/{$campaign->slug}/segments/preview", [
            'criteria' => apiV1SegmentEmailContains('@gmail.com'),
        ])
        ->assertOk()
        ->assertJsonPath('data.count', 2)
        ->assertJsonCount(2, 'data.contacts')
        ->assertJsonStructure(['data' => ['count', 'contacts' => [['id', 'name', 'email']]]]);
});

test('preview only sees the route campaign contacts', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1SegmentMember($campaign, Role::Staffer);
    Contact::factory()->for($campaign)->create(['email' => 'mine@gmail.com']);
    Contact::factory()->for(Campaign::factory())->create(['email' => 'theirs@gmail.com']);

    $this->withToken(apiV1SegmentToken($staffer))
        ->postJson("/api/v1/campaigns/{$campaign->slug}/segments/preview", [
            'criteria' => apiV1SegmentEmailContains('@gmail.com'),
        ])
        ->assertOk()
        ->assertJsonPath('data.count', 1);
});

test('preview rejects off-schema criteria', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1SegmentMember($campaign, Role::Staffer);

    $this->withToken(apiV1SegmentToken($staffer))
        ->postJson("/api/v1/campaigns/{$campaign->slug}/segments/preview", [
            'criteria' => ['rules' => [['field' => 'ssn', 'operator' => 'equals', 'value' => '1']]],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['criteria.rules.0.field']);
});
