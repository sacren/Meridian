<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * Attach $user to $campaign with the given role, returning the user.
 */
function segmentPageMember(Campaign $campaign, Role $role): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $user;
}

test('the segment index renders the page with the paginated segment prop shape', function () {
    $campaign = Campaign::factory()->create();
    $viewer = segmentPageMember($campaign, Role::Viewer);
    Segment::factory()->for($campaign)->create([
        'name' => 'Gmail users',
        'criteria' => ['combinator' => 'and', 'rules' => [
            ['field' => 'email', 'operator' => 'contains', 'value' => '@gmail.com'],
        ]],
    ]);

    $this->actingAs($viewer)
        ->get(route('campaigns.segments.index', $campaign))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Segments/Index')
            ->where('campaign.slug', $campaign->slug)
            ->has('segments.data', 1, fn (AssertableInertia $segment) => $segment
                ->where('name', 'Gmail users')
                ->where('criteria.combinator', 'and')
                ->has('criteria.rules', 1)
                ->etc()
            )
            ->has('segments.links')
            ->where('segments.total', 1)
            ->missing('preview')
        );
});

test('the preview action renders the page with a preview prop', function () {
    $campaign = Campaign::factory()->create();
    $staffer = segmentPageMember($campaign, Role::Staffer);
    Contact::factory()->for($campaign)->create(['email' => 'ada@gmail.com']);
    Contact::factory()->for($campaign)->create(['email' => 'bob@yahoo.com']);

    $this->actingAs($staffer)
        ->post(route('campaigns.segments.preview', $campaign), [
            'criteria' => ['combinator' => 'and', 'rules' => [
                ['field' => 'email', 'operator' => 'contains', 'value' => '@gmail.com'],
            ]],
        ])
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Segments/Index')
            ->where('preview.count', 1)
            ->has('preview.contacts', 1, fn (AssertableInertia $contact) => $contact
                ->where('email', 'ada@gmail.com')
                ->hasAll(['id', 'name', 'phone'])
            )
        );
});
