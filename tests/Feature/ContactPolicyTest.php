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
function memberWithRole(Campaign $campaign, Role $role): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $user;
}

test('an owner may view and manage the campaign contacts', function () {
    $campaign = Campaign::factory()->create();
    $owner = memberWithRole($campaign, Role::Owner);
    $contact = Contact::factory()->for($campaign)->create();

    expect($owner->can('viewAny', [Contact::class, $campaign]))->toBeTrue()
        ->and($owner->can('create', [Contact::class, $campaign]))->toBeTrue()
        ->and($owner->can('view', $contact))->toBeTrue()
        ->and($owner->can('update', $contact))->toBeTrue()
        ->and($owner->can('delete', $contact))->toBeTrue();
});

test('a staffer may view and manage the campaign contacts', function () {
    $campaign = Campaign::factory()->create();
    $staffer = memberWithRole($campaign, Role::Staffer);
    $contact = Contact::factory()->for($campaign)->create();

    expect($staffer->can('viewAny', [Contact::class, $campaign]))->toBeTrue()
        ->and($staffer->can('create', [Contact::class, $campaign]))->toBeTrue()
        ->and($staffer->can('view', $contact))->toBeTrue()
        ->and($staffer->can('update', $contact))->toBeTrue()
        ->and($staffer->can('delete', $contact))->toBeTrue();
});

test('a viewer may read but not manage the campaign contacts', function () {
    $campaign = Campaign::factory()->create();
    $viewer = memberWithRole($campaign, Role::Viewer);
    $contact = Contact::factory()->for($campaign)->create();

    expect($viewer->can('viewAny', [Contact::class, $campaign]))->toBeTrue()
        ->and($viewer->can('view', $contact))->toBeTrue()
        ->and($viewer->can('create', [Contact::class, $campaign]))->toBeFalse()
        ->and($viewer->can('update', $contact))->toBeFalse()
        ->and($viewer->can('delete', $contact))->toBeFalse();
});

test('a non-member is denied every contact action', function () {
    $campaign = Campaign::factory()->create();
    $stranger = User::factory()->create();
    $contact = Contact::factory()->for($campaign)->create();

    expect($stranger->can('viewAny', [Contact::class, $campaign]))->toBeFalse()
        ->and($stranger->can('create', [Contact::class, $campaign]))->toBeFalse()
        ->and($stranger->can('view', $contact))->toBeFalse()
        ->and($stranger->can('update', $contact))->toBeFalse()
        ->and($stranger->can('delete', $contact))->toBeFalse();
});

test('membership in one campaign does not authorize the contacts of another', function () {
    $home = Campaign::factory()->create();
    $other = Campaign::factory()->create();
    $owner = memberWithRole($home, Role::Owner);
    $foreignContact = Contact::factory()->for($other)->create();

    expect($owner->can('viewAny', [Contact::class, $other]))->toBeFalse()
        ->and($owner->can('create', [Contact::class, $other]))->toBeFalse()
        ->and($owner->can('view', $foreignContact))->toBeFalse()
        ->and($owner->can('update', $foreignContact))->toBeFalse()
        ->and($owner->can('delete', $foreignContact))->toBeFalse();
});
