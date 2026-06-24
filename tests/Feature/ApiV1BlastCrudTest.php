<?php

use App\Enums\Role;
use App\Models\Blast;
use App\Models\Campaign;
use App\Models\Segment;
use App\Models\User;

/**
 * Attach a new user to the campaign with the given role and return the user.
 */
function apiV1BlastMember(Campaign $campaign, Role $role): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $user;
}

/**
 * Mint a bearer token for the given user.
 */
function apiV1BlastToken(User $user): string
{
    return $user->createToken('blast-crud-test')->plainTextToken;
}

test('index lists only the route campaign\'s blasts in a paginated envelope', function () {
    $campaign = Campaign::factory()->create();
    $viewer = apiV1BlastMember($campaign, Role::Viewer);
    Blast::factory()->for($campaign)->count(2)->create();
    Blast::factory()->for(Campaign::factory())->count(3)->create();

    $this->withToken(apiV1BlastToken($viewer))
        ->getJson("/api/v1/campaigns/{$campaign->slug}/blasts")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.status', 'draft')
        ->assertJsonStructure([
            'data' => [['id', 'subject', 'body', 'status', 'segment_id', 'segment' => ['id', 'name'], 'created_at']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

test('index requires authentication', function () {
    $campaign = Campaign::factory()->create();

    $this->getJson("/api/v1/campaigns/{$campaign->slug}/blasts")
        ->assertUnauthorized();
});

test('a non-member is forbidden from the blast index', function () {
    $campaign = Campaign::factory()->create();
    $stranger = User::factory()->create();

    $this->withToken(apiV1BlastToken($stranger))
        ->getJson("/api/v1/campaigns/{$campaign->slug}/blasts")
        ->assertForbidden();
});

test('a staffer can create a draft blast and the campaign comes from the route', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();
    $staffer = apiV1BlastMember($campaign, Role::Staffer);

    $this->withToken(apiV1BlastToken($staffer))
        ->postJson("/api/v1/campaigns/{$campaign->slug}/blasts", [
            'campaign_id' => $other->id,
            'subject' => 'Welcome aboard',
            'body' => 'Thanks for joining us.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.subject', 'Welcome aboard')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.segment_id', null);

    $blast = Blast::firstWhere('subject', 'Welcome aboard');

    expect($blast->campaign_id)->toBe($campaign->id);
});

test('a blast may target a segment from the same campaign', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1BlastMember($campaign, Role::Staffer);
    $segment = Segment::factory()->for($campaign)->create();

    $this->withToken(apiV1BlastToken($staffer))
        ->postJson("/api/v1/campaigns/{$campaign->slug}/blasts", [
            'subject' => 'Targeted',
            'body' => 'Hello segment.',
            'segment_id' => $segment->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.segment_id', $segment->id)
        ->assertJsonPath('data.segment.name', $segment->name);
});

test('a blast cannot target a segment from another campaign', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1BlastMember($campaign, Role::Staffer);
    $foreignSegment = Segment::factory()->for(Campaign::factory())->create();

    $this->withToken(apiV1BlastToken($staffer))
        ->postJson("/api/v1/campaigns/{$campaign->slug}/blasts", [
            'subject' => 'Cross-tenant',
            'body' => 'Should be rejected.',
            'segment_id' => $foreignSegment->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['segment_id']);

    expect(Blast::count())->toBe(0);
});

test('creating a blast requires a subject and a body', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1BlastMember($campaign, Role::Staffer);

    $this->withToken(apiV1BlastToken($staffer))
        ->postJson("/api/v1/campaigns/{$campaign->slug}/blasts", [
            'subject' => '',
            'body' => '',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['subject', 'body']);

    expect(Blast::count())->toBe(0);
});

test('an injected status is ignored and the blast stays a draft', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1BlastMember($campaign, Role::Staffer);

    $this->withToken(apiV1BlastToken($staffer))
        ->postJson("/api/v1/campaigns/{$campaign->slug}/blasts", [
            'subject' => 'Status attempt',
            'body' => 'Trying to set status.',
            'status' => 'sent',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft');

    expect(Blast::firstWhere('subject', 'Status attempt')->status->value)->toBe('draft');
});

test('a staffer can update a blast', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1BlastMember($campaign, Role::Staffer);
    $blast = Blast::factory()->for($campaign)->create();

    $this->withToken(apiV1BlastToken($staffer))
        ->putJson("/api/v1/campaigns/{$campaign->slug}/blasts/{$blast->id}", [
            'subject' => 'Revised subject',
            'body' => 'Revised body.',
        ])
        ->assertOk()
        ->assertJsonPath('data.subject', 'Revised subject');

    expect($blast->fresh()->subject)->toBe('Revised subject');
});

test('a staffer can delete a blast', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1BlastMember($campaign, Role::Staffer);
    $blast = Blast::factory()->for($campaign)->create();

    $this->withToken(apiV1BlastToken($staffer))
        ->deleteJson("/api/v1/campaigns/{$campaign->slug}/blasts/{$blast->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('blasts', ['id' => $blast->id]);
});

test('a viewer may list but may not create, update or delete blasts', function () {
    $campaign = Campaign::factory()->create();
    $viewer = apiV1BlastMember($campaign, Role::Viewer);
    $blast = Blast::factory()->for($campaign)->create();
    $token = apiV1BlastToken($viewer);

    $this->withToken($token)
        ->getJson("/api/v1/campaigns/{$campaign->slug}/blasts")
        ->assertOk();

    $this->withToken($token)
        ->postJson("/api/v1/campaigns/{$campaign->slug}/blasts", [
            'subject' => 'Nope',
            'body' => 'Nope.',
        ])
        ->assertForbidden();

    $this->withToken($token)
        ->putJson("/api/v1/campaigns/{$campaign->slug}/blasts/{$blast->id}", [
            'subject' => 'Nope',
            'body' => 'Nope.',
        ])
        ->assertForbidden();

    $this->withToken($token)
        ->deleteJson("/api/v1/campaigns/{$campaign->slug}/blasts/{$blast->id}")
        ->assertForbidden();
});

test('a blast from another campaign cannot be reached through this campaign', function () {
    $campaign = Campaign::factory()->create();
    $staffer = apiV1BlastMember($campaign, Role::Staffer);
    $foreign = Blast::factory()->for(Campaign::factory())->create();

    $this->withToken(apiV1BlastToken($staffer))
        ->putJson("/api/v1/campaigns/{$campaign->slug}/blasts/{$foreign->id}", [
            'subject' => 'Hijacked',
            'body' => 'Hijacked.',
        ])
        ->assertNotFound();
});
