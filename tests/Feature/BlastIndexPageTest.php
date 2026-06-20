<?php

use App\Enums\Role;
use App\Models\Blast;
use App\Models\Campaign;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * Attach $user to $campaign with the given role, returning the user.
 */
function blastPageMember(Campaign $campaign, Role $role): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $user;
}

test('the blast index renders the page with the paginated blast prop shape', function () {
    $campaign = Campaign::factory()->create();
    $viewer = blastPageMember($campaign, Role::Viewer);
    $segment = Segment::factory()->for($campaign)->create(['name' => 'Gmail users']);
    Blast::factory()->for($campaign)->create([
        'subject' => 'Spring update',
        'body' => 'Here is what is new.',
        'segment_id' => $segment->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('campaigns.blasts.index', $campaign))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Blasts/Index')
            ->where('campaign.slug', $campaign->slug)
            ->has('blasts.data', 1, fn (AssertableInertia $blast) => $blast
                ->where('subject', 'Spring update')
                ->where('status', 'draft')
                ->where('segment_id', $segment->id)
                ->where('segment.name', 'Gmail users')
                ->etc()
            )
            ->has('blasts.links')
            ->where('blasts.total', 1)
        );
});

test('the blast index exposes the campaign segments as targets', function () {
    $campaign = Campaign::factory()->create();
    $staffer = blastPageMember($campaign, Role::Staffer);
    Segment::factory()->for($campaign)->create(['name' => 'Texans']);
    Segment::factory()->for(Campaign::factory())->create(['name' => 'Foreigners']);

    $this->actingAs($staffer)
        ->get(route('campaigns.blasts.index', $campaign))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Blasts/Index')
            ->has('segments', 1, fn (AssertableInertia $segment) => $segment
                ->where('name', 'Texans')
                ->hasAll(['id', 'name'])
            )
        );
});
