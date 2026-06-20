<?php

use App\Enums\Role;
use App\Models\Blast;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Attach $user to $campaign with the given role, returning the user.
 */
function blastMember(Campaign $campaign, Role $role): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $user;
}

test('an owner may view and manage the campaign blasts', function () {
    $campaign = Campaign::factory()->create();
    $owner = blastMember($campaign, Role::Owner);
    $blast = Blast::factory()->for($campaign)->create();

    expect($owner->can('viewAny', [Blast::class, $campaign]))->toBeTrue()
        ->and($owner->can('create', [Blast::class, $campaign]))->toBeTrue()
        ->and($owner->can('view', $blast))->toBeTrue()
        ->and($owner->can('update', $blast))->toBeTrue()
        ->and($owner->can('delete', $blast))->toBeTrue();
});

test('a staffer may view and manage the campaign blasts', function () {
    $campaign = Campaign::factory()->create();
    $staffer = blastMember($campaign, Role::Staffer);
    $blast = Blast::factory()->for($campaign)->create();

    expect($staffer->can('viewAny', [Blast::class, $campaign]))->toBeTrue()
        ->and($staffer->can('create', [Blast::class, $campaign]))->toBeTrue()
        ->and($staffer->can('view', $blast))->toBeTrue()
        ->and($staffer->can('update', $blast))->toBeTrue()
        ->and($staffer->can('delete', $blast))->toBeTrue();
});

test('a viewer may read but not manage the campaign blasts', function () {
    $campaign = Campaign::factory()->create();
    $viewer = blastMember($campaign, Role::Viewer);
    $blast = Blast::factory()->for($campaign)->create();

    expect($viewer->can('viewAny', [Blast::class, $campaign]))->toBeTrue()
        ->and($viewer->can('view', $blast))->toBeTrue()
        ->and($viewer->can('create', [Blast::class, $campaign]))->toBeFalse()
        ->and($viewer->can('update', $blast))->toBeFalse()
        ->and($viewer->can('delete', $blast))->toBeFalse();
});

test('a non-member is denied every blast action', function () {
    $campaign = Campaign::factory()->create();
    $stranger = User::factory()->create();
    $blast = Blast::factory()->for($campaign)->create();

    expect($stranger->can('viewAny', [Blast::class, $campaign]))->toBeFalse()
        ->and($stranger->can('create', [Blast::class, $campaign]))->toBeFalse()
        ->and($stranger->can('view', $blast))->toBeFalse()
        ->and($stranger->can('update', $blast))->toBeFalse()
        ->and($stranger->can('delete', $blast))->toBeFalse();
});

test('membership in one campaign does not authorize the blasts of another', function () {
    $home = Campaign::factory()->create();
    $other = Campaign::factory()->create();
    $owner = blastMember($home, Role::Owner);
    $foreignBlast = Blast::factory()->for($other)->create();

    expect($owner->can('viewAny', [Blast::class, $other]))->toBeFalse()
        ->and($owner->can('create', [Blast::class, $other]))->toBeFalse()
        ->and($owner->can('view', $foreignBlast))->toBeFalse()
        ->and($owner->can('update', $foreignBlast))->toBeFalse()
        ->and($owner->can('delete', $foreignBlast))->toBeFalse();
});
