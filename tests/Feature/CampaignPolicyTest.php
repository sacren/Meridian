<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\User;

test('an owner may update the campaign and manage its members', function () {
    $owner = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $campaign->users()->attach($owner, ['role' => Role::Owner->value]);

    expect($owner->can('update', $campaign))->toBeTrue();
    expect($owner->can('manageMembers', $campaign))->toBeTrue();
});

test('a staffer may not update the campaign or manage its members', function () {
    $staffer = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $campaign->users()->attach($staffer, ['role' => Role::Staffer->value]);

    expect($staffer->can('update', $campaign))->toBeFalse();
    expect($staffer->can('manageMembers', $campaign))->toBeFalse();
});

test('a viewer may not update the campaign or manage its members', function () {
    $viewer = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $campaign->users()->attach($viewer, ['role' => Role::Viewer->value]);

    expect($viewer->can('update', $campaign))->toBeFalse();
    expect($viewer->can('manageMembers', $campaign))->toBeFalse();
});

test('a non-member is denied every campaign action', function () {
    $stranger = User::factory()->create();
    $campaign = Campaign::factory()->create();

    expect($stranger->can('update', $campaign))->toBeFalse();
    expect($stranger->can('manageMembers', $campaign))->toBeFalse();
});
