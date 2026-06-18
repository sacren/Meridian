<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Attach $user to $campaign with the given role, returning the user.
 */
function segmentMember(Campaign $campaign, Role $role): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $user;
}

test('an owner may view and manage the campaign segments', function () {
    $campaign = Campaign::factory()->create();
    $owner = segmentMember($campaign, Role::Owner);
    $segment = Segment::factory()->for($campaign)->create();

    expect($owner->can('viewAny', [Segment::class, $campaign]))->toBeTrue()
        ->and($owner->can('create', [Segment::class, $campaign]))->toBeTrue()
        ->and($owner->can('view', $segment))->toBeTrue()
        ->and($owner->can('update', $segment))->toBeTrue()
        ->and($owner->can('delete', $segment))->toBeTrue();
});

test('a staffer may view and manage the campaign segments', function () {
    $campaign = Campaign::factory()->create();
    $staffer = segmentMember($campaign, Role::Staffer);
    $segment = Segment::factory()->for($campaign)->create();

    expect($staffer->can('viewAny', [Segment::class, $campaign]))->toBeTrue()
        ->and($staffer->can('create', [Segment::class, $campaign]))->toBeTrue()
        ->and($staffer->can('view', $segment))->toBeTrue()
        ->and($staffer->can('update', $segment))->toBeTrue()
        ->and($staffer->can('delete', $segment))->toBeTrue();
});

test('a viewer may read but not manage the campaign segments', function () {
    $campaign = Campaign::factory()->create();
    $viewer = segmentMember($campaign, Role::Viewer);
    $segment = Segment::factory()->for($campaign)->create();

    expect($viewer->can('viewAny', [Segment::class, $campaign]))->toBeTrue()
        ->and($viewer->can('view', $segment))->toBeTrue()
        ->and($viewer->can('create', [Segment::class, $campaign]))->toBeFalse()
        ->and($viewer->can('update', $segment))->toBeFalse()
        ->and($viewer->can('delete', $segment))->toBeFalse();
});

test('a non-member is denied every segment action', function () {
    $campaign = Campaign::factory()->create();
    $stranger = User::factory()->create();
    $segment = Segment::factory()->for($campaign)->create();

    expect($stranger->can('viewAny', [Segment::class, $campaign]))->toBeFalse()
        ->and($stranger->can('create', [Segment::class, $campaign]))->toBeFalse()
        ->and($stranger->can('view', $segment))->toBeFalse()
        ->and($stranger->can('update', $segment))->toBeFalse()
        ->and($stranger->can('delete', $segment))->toBeFalse();
});

test('membership in one campaign does not authorize the segments of another', function () {
    $home = Campaign::factory()->create();
    $other = Campaign::factory()->create();
    $owner = segmentMember($home, Role::Owner);
    $foreignSegment = Segment::factory()->for($other)->create();

    expect($owner->can('viewAny', [Segment::class, $other]))->toBeFalse()
        ->and($owner->can('create', [Segment::class, $other]))->toBeFalse()
        ->and($owner->can('view', $foreignSegment))->toBeFalse()
        ->and($owner->can('update', $foreignSegment))->toBeFalse()
        ->and($owner->can('delete', $foreignSegment))->toBeFalse();
});
