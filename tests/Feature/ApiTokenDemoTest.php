<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

/**
 * Attach $user to $campaign with the given role, returning the user.
 */
function apiTokenDemoMember(Campaign $campaign, Role $role): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $user;
}

test('the API console renders with the campaigns the user belongs to', function () {
    $campaign = Campaign::factory()->create(['name' => 'Acme Outreach']);
    $user = apiTokenDemoMember($campaign, Role::Owner);

    $this->actingAs($user)
        ->get(route('api-token.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ApiDemo/Index')
            ->has('campaigns', 1, fn (AssertableInertia $listed) => $listed
                ->where('id', $campaign->id)
                ->where('name', 'Acme Outreach')
                ->where('slug', $campaign->slug)
                ->where('role', Role::Owner->value)
            )
        );
});

test('a session-authenticated user is issued a working bearer token', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('api-token.generate'));

    $response->assertOk()
        ->assertJsonStructure(['token', 'token_type'])
        ->assertJsonPath('token_type', 'Bearer');

    $token = $response->json('token');

    // The freshly minted token authenticates an auth:sanctum route as this user.
    $this->withToken($token)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

test('a guest is redirected to login rather than receiving a JSON 401', function () {
    // A plain (non-JSON) request exercises the web session guard, which redirects
    // unauthenticated visitors to the login page instead of returning a 401.
    $this->post(route('api-token.generate'))
        ->assertRedirect(route('login'));

    expect(PersonalAccessToken::count())->toBe(0);
});

test('a guest cannot view the API console', function () {
    $this->get(route('api-token.create'))
        ->assertRedirect(route('login'));
});
