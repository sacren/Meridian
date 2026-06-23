<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\User;

/**
 * Attach the user to a freshly made campaign with the given role and return the campaign.
 */
function apiV1CampaignReadMembership(User $user, Role $role = Role::Owner): Campaign
{
    $campaign = Campaign::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $campaign;
}

/**
 * Mint a bearer token for the given user.
 */
function apiV1CampaignReadToken(User $user): string
{
    return $user->createToken('campaign-read-test')->plainTextToken;
}

test('index lists only the calling token owner\'s campaigns', function () {
    $user = User::factory()->create();
    $mine = apiV1CampaignReadMembership($user, Role::Owner);
    $alsoMine = apiV1CampaignReadMembership($user, Role::Viewer);

    // A campaign belonging to a stranger must never leak into the listing.
    apiV1CampaignReadMembership(User::factory()->create(), Role::Owner);

    $response = $this->withToken(apiV1CampaignReadToken($user))
        ->getJson('/api/v1/campaigns');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'created_at', 'role']]]);

    expect(collect($response->json('data'))->pluck('slug')->all())
        ->toEqualCanonicalizing([$mine->slug, $alsoMine->slug]);
});

test('index exposes the calling user\'s role on each row', function () {
    $user = User::factory()->create();
    apiV1CampaignReadMembership($user, Role::Staffer);

    $this->withToken(apiV1CampaignReadToken($user))
        ->getJson('/api/v1/campaigns')
        ->assertOk()
        ->assertJsonPath('data.0.role', Role::Staffer->value);
});

test('index requires authentication', function () {
    $this->getJson('/api/v1/campaigns')->assertUnauthorized();
});

test('show returns the campaign for a member without a role field', function () {
    $user = User::factory()->create();
    $campaign = apiV1CampaignReadMembership($user, Role::Owner);

    $this->withToken(apiV1CampaignReadToken($user))
        ->getJson("/api/v1/campaigns/{$campaign->slug}")
        ->assertOk()
        ->assertJsonPath('data.id', $campaign->id)
        ->assertJsonPath('data.name', $campaign->name)
        ->assertJsonPath('data.slug', $campaign->slug)
        ->assertJsonMissingPath('data.role');
});

test('show is forbidden for a non-member', function () {
    $user = User::factory()->create();
    apiV1CampaignReadMembership($user, Role::Owner);

    // The caller is a member of their own campaign but a stranger to this one.
    $theirs = apiV1CampaignReadMembership(User::factory()->create(), Role::Owner);

    $this->withToken(apiV1CampaignReadToken($user))
        ->getJson("/api/v1/campaigns/{$theirs->slug}")
        ->assertForbidden();
});

test('show is not found for an unknown slug', function () {
    $user = User::factory()->create();

    $this->withToken(apiV1CampaignReadToken($user))
        ->getJson('/api/v1/campaigns/does-not-exist')
        ->assertNotFound();
});
