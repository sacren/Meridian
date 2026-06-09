<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('roleIn returns the pivot role for a member', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();

    $campaign->users()->attach($user, ['role' => Role::Staffer->value]);

    expect($user->roleIn($campaign))->toBe(Role::Staffer);
    expect($user->belongsToCampaign($campaign))->toBeTrue();
});

test('a non-member has no role and does not belong to the campaign', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();

    expect($user->roleIn($campaign))->toBeNull();
    expect($user->belongsToCampaign($campaign))->toBeFalse();
});

test('a user cannot be attached to the same campaign twice', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();

    $campaign->users()->attach($user, ['role' => Role::Owner->value]);
    $campaign->users()->attach($user, ['role' => Role::Viewer->value]);
})->throws(QueryException::class);

test('the relationship exposes the role on the pivot', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $campaign->users()->attach($user, ['role' => Role::Owner->value]);

    expect($campaign->users()->first()->pivot->role)->toBe(Role::Owner->value);
    expect($user->campaigns()->first()->pivot->role)->toBe(Role::Owner->value);
});
