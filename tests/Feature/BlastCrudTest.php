<?php

use App\Enums\BlastStatus;
use App\Enums\Role;
use App\Models\Blast;
use App\Models\Campaign;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * Attach $user to $campaign with the given role, returning the user.
 */
function blastCrudMember(Campaign $campaign, Role $role): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $user;
}

test('a member can list only the blasts of the route campaign', function () {
    $campaign = Campaign::factory()->create();
    $viewer = blastCrudMember($campaign, Role::Viewer);
    Blast::factory()->for($campaign)->count(2)->create();
    Blast::factory()->for(Campaign::factory())->count(3)->create();

    $this->actingAs($viewer)
        ->get(route('campaigns.blasts.index', $campaign))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Blasts/Index', false)
            ->has('blasts.data', 2)
            ->has('segments')
        );
});

test('a staffer can create a draft blast through the campaign', function () {
    $campaign = Campaign::factory()->create();
    $staffer = blastCrudMember($campaign, Role::Staffer);
    $segment = Segment::factory()->for($campaign)->create();

    $this->actingAs($staffer)
        ->post(route('campaigns.blasts.store', $campaign), [
            'subject' => 'Spring update',
            'body' => 'Here is what is new this spring.',
            'segment_id' => $segment->id,
        ])
        ->assertRedirect(route('campaigns.blasts.index', $campaign));

    $blast = Blast::firstWhere('subject', 'Spring update');

    expect($blast->campaign_id)->toBe($campaign->id)
        ->and($blast->segment_id)->toBe($segment->id)
        ->and($blast->status)->toBe(BlastStatus::Draft);
});

test('the store campaign is taken from the route, never from input', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();
    $staffer = blastCrudMember($campaign, Role::Staffer);

    $this->actingAs($staffer)
        ->post(route('campaigns.blasts.store', $campaign), [
            'campaign_id' => $other->id,
            'subject' => 'Routed blast',
            'body' => 'Body copy.',
        ]);

    expect(Blast::firstWhere('subject', 'Routed blast')->campaign_id)->toBe($campaign->id);
});

test('a blast may be created with no target segment', function () {
    $campaign = Campaign::factory()->create();
    $staffer = blastCrudMember($campaign, Role::Staffer);

    $this->actingAs($staffer)
        ->post(route('campaigns.blasts.store', $campaign), [
            'subject' => 'No target yet',
            'body' => 'Drafting before choosing an audience.',
        ])
        ->assertRedirect(route('campaigns.blasts.index', $campaign));

    expect(Blast::firstWhere('subject', 'No target yet')->segment_id)->toBeNull();
});

test('storing a blast requires a subject and a body', function () {
    $campaign = Campaign::factory()->create();
    $staffer = blastCrudMember($campaign, Role::Staffer);
    $url = route('campaigns.blasts.store', $campaign);

    $this->actingAs($staffer)
        ->from(route('campaigns.blasts.index', $campaign))
        ->post($url, ['subject' => '', 'body' => ''])
        ->assertInvalid(['subject', 'body']);

    expect(Blast::count())->toBe(0);
});

test('a target segment from another campaign is rejected', function () {
    $campaign = Campaign::factory()->create();
    $staffer = blastCrudMember($campaign, Role::Staffer);
    $foreignSegment = Segment::factory()->for(Campaign::factory())->create();

    $this->actingAs($staffer)
        ->from(route('campaigns.blasts.index', $campaign))
        ->post(route('campaigns.blasts.store', $campaign), [
            'subject' => 'Cross-tenant',
            'body' => 'Trying to target another campaign segment.',
            'segment_id' => $foreignSegment->id,
        ])
        ->assertInvalid(['segment_id']);

    expect(Blast::count())->toBe(0);
});

test('a staffer can update a blast and retarget it within the campaign', function () {
    $campaign = Campaign::factory()->create();
    $staffer = blastCrudMember($campaign, Role::Staffer);
    $blast = Blast::factory()->for($campaign)->create();
    $newTarget = Segment::factory()->for($campaign)->create();

    $this->actingAs($staffer)
        ->put(route('campaigns.blasts.update', [$campaign, $blast]), [
            'subject' => 'Revised subject',
            'body' => 'Revised body copy.',
            'segment_id' => $newTarget->id,
        ])
        ->assertRedirect(route('campaigns.blasts.index', $campaign));

    expect($blast->fresh())
        ->subject->toBe('Revised subject')
        ->segment_id->toBe($newTarget->id)
        ->status->toBe(BlastStatus::Draft);
});

test('updating a blast cannot retarget it to another campaign segment', function () {
    $campaign = Campaign::factory()->create();
    $staffer = blastCrudMember($campaign, Role::Staffer);
    $blast = Blast::factory()->for($campaign)->create();
    $foreignSegment = Segment::factory()->for(Campaign::factory())->create();

    $this->actingAs($staffer)
        ->from(route('campaigns.blasts.index', $campaign))
        ->put(route('campaigns.blasts.update', [$campaign, $blast]), [
            'subject' => 'Revised subject',
            'body' => 'Revised body copy.',
            'segment_id' => $foreignSegment->id,
        ])
        ->assertInvalid(['segment_id']);
});

test('a staffer can delete a blast', function () {
    $campaign = Campaign::factory()->create();
    $staffer = blastCrudMember($campaign, Role::Staffer);
    $blast = Blast::factory()->for($campaign)->create();

    $this->actingAs($staffer)
        ->delete(route('campaigns.blasts.destroy', [$campaign, $blast]))
        ->assertRedirect(route('campaigns.blasts.index', $campaign));

    $this->assertDatabaseMissing('blasts', ['id' => $blast->id]);
});

test('a viewer may list but may not create, update or delete blasts', function () {
    $campaign = Campaign::factory()->create();
    $viewer = blastCrudMember($campaign, Role::Viewer);
    $blast = Blast::factory()->for($campaign)->create();

    $this->actingAs($viewer)->get(route('campaigns.blasts.index', $campaign))->assertOk();

    $this->actingAs($viewer)
        ->post(route('campaigns.blasts.store', $campaign), ['subject' => 'Nope', 'body' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->put(route('campaigns.blasts.update', [$campaign, $blast]), ['subject' => 'Nope', 'body' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->delete(route('campaigns.blasts.destroy', [$campaign, $blast]))
        ->assertForbidden();
});

test('a non-member is denied the blast index', function () {
    $campaign = Campaign::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('campaigns.blasts.index', $campaign))
        ->assertForbidden();
});

test('a blast from another campaign cannot be updated through this campaign', function () {
    $campaign = Campaign::factory()->create();
    $staffer = blastCrudMember($campaign, Role::Staffer);
    $foreign = Blast::factory()->for(Campaign::factory())->create();

    $this->actingAs($staffer)
        ->put(route('campaigns.blasts.update', [$campaign, $foreign]), [
            'subject' => 'Hijacked',
            'body' => 'Hijacked body.',
        ])
        ->assertNotFound();
});
