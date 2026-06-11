<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'campaign.access'])
        ->get('/campaigns/{campaign}/probe', fn (Request $request, Campaign $campaign) => response()->json([
            'campaign' => $request->attributes->get('campaign')->slug,
            'role' => $request->attributes->get('role')->value,
        ]));
});

test('a member reaches the route, with the campaign and role exposed on the request', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $campaign->users()->attach($user, ['role' => Role::Viewer->value]);

    $this->actingAs($user)
        ->get("/campaigns/{$campaign->slug}/probe")
        ->assertOk()
        ->assertExactJson(['campaign' => $campaign->slug, 'role' => Role::Viewer->value]);
});

test('a member of a different campaign is forbidden', function () {
    $user = User::factory()->create();
    $theirs = Campaign::factory()->create();
    $theirs->users()->attach($user, ['role' => Role::Owner->value]);
    $other = Campaign::factory()->create();

    $this->actingAs($user)
        ->get("/campaigns/{$other->slug}/probe")
        ->assertForbidden();
});

test('a guest is redirected to login', function () {
    $campaign = Campaign::factory()->create();

    $this->get("/campaigns/{$campaign->slug}/probe")
        ->assertRedirect(route('login'));
});

test('an unknown campaign slug 404s before the membership check', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/campaigns/does-not-exist/probe')
        ->assertNotFound();
});
