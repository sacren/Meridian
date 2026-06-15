<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\User;

test('an authenticated user can create a campaign and becomes its sole owner', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('campaigns.store'), [
        'name' => 'Acme Outreach',
    ]);

    $campaign = Campaign::firstWhere('name', 'Acme Outreach');

    expect($campaign)->not->toBeNull();
    $response->assertRedirect(route('campaigns.dashboard', $campaign));

    $owners = $campaign->users()->wherePivot('role', Role::Owner->value)->pluck('users.id')->all();
    expect($owners)->toBe([$user->id]);
});

test('creating a campaign requires a name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('campaigns.store'), ['name' => ''])
        ->assertInvalid(['name'])
        ->assertRedirect(route('dashboard'));

    expect(Campaign::count())->toBe(0);
});

test('a campaign name may be 255 characters but no more', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('campaigns.store'), ['name' => str_repeat('a', 256)])
        ->assertInvalid(['name'])
        ->assertRedirect(route('dashboard'));

    expect(Campaign::count())->toBe(0);

    $name = str_repeat('a', 255);

    $this->actingAs($user)->post(route('campaigns.store'), ['name' => $name]);

    expect(Campaign::firstWhere('name', $name))->not->toBeNull();
});

test('campaigns created with the same name get distinct slugs', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('campaigns.store'), ['name' => 'Spring Drive']);
    $this->actingAs($user)->post(route('campaigns.store'), ['name' => 'Spring Drive']);

    $slugs = Campaign::where('name', 'Spring Drive')->orderBy('id')->pluck('slug');

    expect($slugs->all())->toBe(['spring-drive', 'spring-drive-2']);
});

test('a guest cannot create a campaign', function () {
    $this->post(route('campaigns.store'), ['name' => 'Acme Outreach'])
        ->assertRedirect(route('login'));

    expect(Campaign::count())->toBe(0);
});

test('the redirect target dashboard is reachable by the owner', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $campaign->users()->attach($user, ['role' => Role::Owner->value]);

    // The dashboard's prop shape and Vue page are asserted in L7; here we only
    // prove the creation redirect lands on a real, boundary-protected route.
    $this->actingAs($user)
        ->get(route('campaigns.dashboard', $campaign))
        ->assertOk();
});
