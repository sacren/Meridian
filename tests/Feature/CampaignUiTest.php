<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('the index lists only the campaigns the user belongs to, ordered by name', function () {
    $user = User::factory()->create();
    $mine = Campaign::factory()->create(['name' => 'Mine']);
    $alsoMine = Campaign::factory()->create(['name' => 'Also Mine']);
    $theirs = Campaign::factory()->create(['name' => 'Theirs']);

    $mine->users()->attach($user, ['role' => Role::Owner->value]);
    $alsoMine->users()->attach($user, ['role' => Role::Viewer->value]);
    $theirs->users()->attach(User::factory()->create(), ['role' => Role::Owner->value]);

    $this->actingAs($user)
        ->get(route('campaigns.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Campaigns/Index')
            ->has('campaigns', 2)
            ->where('campaigns.0.id', $alsoMine->id)
            ->where('campaigns.0.name', 'Also Mine')
            ->where('campaigns.0.slug', $alsoMine->slug)
            ->where('campaigns.0.role', Role::Viewer->value)
            ->where('campaigns.1.id', $mine->id)
            ->where('campaigns.1.name', 'Mine')
            ->where('campaigns.1.slug', $mine->slug)
            ->where('campaigns.1.role', Role::Owner->value)
        );
});

test('the index lists no campaigns when the user belongs to none', function () {
    $user = User::factory()->create();
    Campaign::factory()->create(); // exists, but the user is not a member

    $this->actingAs($user)
        ->get(route('campaigns.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Campaigns/Index')
            ->has('campaigns', 0)
        );
});

test('the campaign dashboard props carry the campaign and the role', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $campaign->users()->attach($user, ['role' => Role::Staffer->value]);

    $this->actingAs($user)
        ->get(route('campaigns.dashboard', $campaign))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Campaigns/Dashboard')
            ->where('campaign.id', $campaign->id)
            ->where('campaign.name', $campaign->name)
            ->where('campaign.slug', $campaign->slug)
            ->where('role', Role::Staffer->value)
            ->missing('campaign.created_at')
        );
});
