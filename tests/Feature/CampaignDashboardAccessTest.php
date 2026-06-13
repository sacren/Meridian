<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\User;

/**
 * Proves the tenant boundary is actually wired onto the real campaigns.dashboard
 * route. EnsureCampaignAccessTest proves the middleware's logic on a throwaway probe
 * route; these guard against the route silently losing its `campaign.access` middleware.
 */
test('a member can reach the campaign dashboard', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $campaign->users()->attach($user, ['role' => Role::Viewer->value]);

    $this->actingAs($user)
        ->get(route('campaigns.dashboard', $campaign))
        ->assertOk();
});

test('a member of a different campaign is forbidden from this campaign dashboard', function () {
    $user = User::factory()->create();
    Campaign::factory()->create()->users()->attach($user, ['role' => Role::Owner->value]);
    $other = Campaign::factory()->create();

    $this->actingAs($user)
        ->get(route('campaigns.dashboard', $other))
        ->assertForbidden();
});

test('a guest is redirected to login from the campaign dashboard', function () {
    $campaign = Campaign::factory()->create();

    $this->get(route('campaigns.dashboard', $campaign))
        ->assertRedirect(route('login'));
});
