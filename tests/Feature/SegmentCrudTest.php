<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * Attach $user to $campaign with the given role, returning the user.
 */
function segmentCrudMember(Campaign $campaign, Role $role): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $user;
}

/**
 * A valid criteria payload matching contacts whose email contains the needle.
 *
 * @return array{combinator: string, rules: array<int, array<string, string>>}
 */
function emailContains(string $needle): array
{
    return [
        'combinator' => 'and',
        'rules' => [
            ['field' => 'email', 'operator' => 'contains', 'value' => $needle],
        ],
    ];
}

test('a member can list only the segments of the route campaign', function () {
    $campaign = Campaign::factory()->create();
    $viewer = segmentCrudMember($campaign, Role::Viewer);
    Segment::factory()->for($campaign)->count(2)->create();
    Segment::factory()->for(Campaign::factory())->count(3)->create();

    $this->actingAs($viewer)
        ->get(route('campaigns.segments.index', $campaign))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Segments/Index')
            ->has('segments.data', 2)
        );
});

test('a staffer can create a segment through the campaign', function () {
    $campaign = Campaign::factory()->create();
    $staffer = segmentCrudMember($campaign, Role::Staffer);

    $this->actingAs($staffer)
        ->post(route('campaigns.segments.store', $campaign), [
            'name' => 'Gmail users',
            'criteria' => emailContains('@gmail.com'),
        ])
        ->assertRedirect(route('campaigns.segments.index', $campaign));

    $segment = Segment::firstWhere('name', 'Gmail users');

    expect($segment->campaign_id)->toBe($campaign->id)
        ->and($segment->criteria['rules'][0]['operator'])->toBe('contains');
});

test('the store campaign is taken from the route, never from input', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();
    $staffer = segmentCrudMember($campaign, Role::Staffer);

    $this->actingAs($staffer)
        ->post(route('campaigns.segments.store', $campaign), [
            'campaign_id' => $other->id,
            'name' => 'Routed segment',
            'criteria' => null,
        ]);

    expect(Segment::firstWhere('name', 'Routed segment')->campaign_id)->toBe($campaign->id);
});

test('a segment may be created with no criteria', function () {
    $campaign = Campaign::factory()->create();
    $staffer = segmentCrudMember($campaign, Role::Staffer);

    $this->actingAs($staffer)
        ->post(route('campaigns.segments.store', $campaign), ['name' => 'Everyone'])
        ->assertRedirect(route('campaigns.segments.index', $campaign));

    expect(Segment::firstWhere('name', 'Everyone'))->not->toBeNull();
});

test('storing a segment requires a name', function () {
    $campaign = Campaign::factory()->create();
    $staffer = segmentCrudMember($campaign, Role::Staffer);

    $this->actingAs($staffer)
        ->from(route('campaigns.segments.index', $campaign))
        ->post(route('campaigns.segments.store', $campaign), ['name' => ''])
        ->assertInvalid(['name']);

    expect(Segment::count())->toBe(0);
});

test('criteria off the schema is rejected', function () {
    $campaign = Campaign::factory()->create();
    $staffer = segmentCrudMember($campaign, Role::Staffer);
    $url = route('campaigns.segments.store', $campaign);

    // Field outside the whitelist.
    $this->actingAs($staffer)->from($url)->post($url, [
        'name' => 'Bad field',
        'criteria' => ['rules' => [['field' => 'id', 'operator' => 'equals', 'value' => '1']]],
    ])->assertInvalid(['criteria.rules.0.field']);

    // Unknown operator.
    $this->actingAs($staffer)->from($url)->post($url, [
        'name' => 'Bad operator',
        'criteria' => ['rules' => [['field' => 'name', 'operator' => 'regex', 'value' => 'x']]],
    ])->assertInvalid(['criteria.rules.0.operator']);

    // Bad combinator.
    $this->actingAs($staffer)->from($url)->post($url, [
        'name' => 'Bad combinator',
        'criteria' => ['combinator' => 'xor', 'rules' => []],
    ])->assertInvalid(['criteria.combinator']);

    expect(Segment::count())->toBe(0);
});

test('a value-bearing operator requires a value, but a presence operator does not', function () {
    $campaign = Campaign::factory()->create();
    $staffer = segmentCrudMember($campaign, Role::Staffer);
    $url = route('campaigns.segments.store', $campaign);

    // contains without a value is invalid.
    $this->actingAs($staffer)->from($url)->post($url, [
        'name' => 'Missing value',
        'criteria' => ['rules' => [['field' => 'name', 'operator' => 'contains']]],
    ])->assertInvalid(['criteria.rules.0.value']);

    // is_empty without a value is valid.
    $this->actingAs($staffer)->post($url, [
        'name' => 'No phone',
        'criteria' => ['rules' => [['field' => 'phone', 'operator' => 'is_empty']]],
    ])->assertRedirect(route('campaigns.segments.index', $campaign));

    expect(Segment::firstWhere('name', 'No phone'))->not->toBeNull();
});

test('a staffer can update a segment', function () {
    $campaign = Campaign::factory()->create();
    $staffer = segmentCrudMember($campaign, Role::Staffer);
    $segment = Segment::factory()->for($campaign)->create();

    $this->actingAs($staffer)
        ->put(route('campaigns.segments.update', [$campaign, $segment]), [
            'name' => 'Renamed segment',
            'criteria' => emailContains('@example.org'),
        ])
        ->assertRedirect(route('campaigns.segments.index', $campaign));

    expect($segment->fresh()->name)->toBe('Renamed segment');
});

test('a staffer can delete a segment', function () {
    $campaign = Campaign::factory()->create();
    $staffer = segmentCrudMember($campaign, Role::Staffer);
    $segment = Segment::factory()->for($campaign)->create();

    $this->actingAs($staffer)
        ->delete(route('campaigns.segments.destroy', [$campaign, $segment]))
        ->assertRedirect(route('campaigns.segments.index', $campaign));

    $this->assertDatabaseMissing('segments', ['id' => $segment->id]);
});

test('a viewer may list but may not create, update or delete segments', function () {
    $campaign = Campaign::factory()->create();
    $viewer = segmentCrudMember($campaign, Role::Viewer);
    $segment = Segment::factory()->for($campaign)->create();

    $this->actingAs($viewer)->get(route('campaigns.segments.index', $campaign))->assertOk();

    $this->actingAs($viewer)
        ->post(route('campaigns.segments.store', $campaign), ['name' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->put(route('campaigns.segments.update', [$campaign, $segment]), ['name' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->delete(route('campaigns.segments.destroy', [$campaign, $segment]))
        ->assertForbidden();
});

test('a non-member is denied the segment index', function () {
    $campaign = Campaign::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('campaigns.segments.index', $campaign))
        ->assertForbidden();
});

test('a segment from another campaign cannot be updated through this campaign', function () {
    $campaign = Campaign::factory()->create();
    $staffer = segmentCrudMember($campaign, Role::Staffer);
    $foreign = Segment::factory()->for(Campaign::factory())->create();

    $this->actingAs($staffer)
        ->put(route('campaigns.segments.update', [$campaign, $foreign]), ['name' => 'Hijacked'])
        ->assertNotFound();
});

test('preview returns the contacts matched by the posted criteria', function () {
    $campaign = Campaign::factory()->create();
    $staffer = segmentCrudMember($campaign, Role::Staffer);
    Contact::factory()->for($campaign)->create(['name' => 'Ada', 'email' => 'ada@gmail.com']);
    Contact::factory()->for($campaign)->create(['name' => 'Bob', 'email' => 'bob@yahoo.com']);
    Contact::factory()->for($campaign)->create(['name' => 'Cy', 'email' => 'cy@gmail.com']);

    $this->actingAs($staffer)
        ->post(route('campaigns.segments.preview', $campaign), ['criteria' => emailContains('@gmail.com')])
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Segments/Index')
            ->where('preview.count', 2)
            ->has('preview.contacts', 2)
        );
});

test('preview only sees the route campaign contacts', function () {
    $campaign = Campaign::factory()->create();
    $staffer = segmentCrudMember($campaign, Role::Staffer);
    Contact::factory()->for($campaign)->create(['email' => 'mine@gmail.com']);
    Contact::factory()->for(Campaign::factory())->create(['email' => 'theirs@gmail.com']);

    $this->actingAs($staffer)
        ->post(route('campaigns.segments.preview', $campaign), ['criteria' => emailContains('@gmail.com')])
        ->assertInertia(fn (AssertableInertia $page) => $page->where('preview.count', 1));
});

test('a non-member is denied the preview', function () {
    $campaign = Campaign::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->post(route('campaigns.segments.preview', $campaign), ['criteria' => emailContains('@gmail.com')])
        ->assertForbidden();
});

test('preview rejects off-schema criteria', function () {
    $campaign = Campaign::factory()->create();
    $staffer = segmentCrudMember($campaign, Role::Staffer);

    $this->actingAs($staffer)
        ->from(route('campaigns.segments.index', $campaign))
        ->post(route('campaigns.segments.preview', $campaign), [
            'criteria' => ['rules' => [['field' => 'ssn', 'operator' => 'equals', 'value' => '1']]],
        ])
        ->assertInvalid(['criteria.rules.0.field']);
});
