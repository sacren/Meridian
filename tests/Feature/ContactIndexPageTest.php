<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * Attach $user to $campaign with the given role, returning the user.
 */
function contactPageMember(Campaign $campaign, Role $role): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $user;
}

test('the contact index renders the page with the paginated contact prop shape', function () {
    $campaign = Campaign::factory()->create();
    $viewer = contactPageMember($campaign, Role::Viewer);
    Contact::factory()->for($campaign)->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    $this->actingAs($viewer)
        ->get(route('campaigns.contacts.index', $campaign))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Contacts/Index')
            ->where('campaign.slug', $campaign->slug)
            ->has('contacts.data', 1, fn (AssertableInertia $contact) => $contact
                ->where('name', 'Ada Lovelace')
                ->where('email', 'ada@example.com')
                ->hasAll(['id', 'phone'])
            )
            ->has('contacts.links')
            ->where('contacts.total', 1)
            ->has('filters', fn (AssertableInertia $filters) => $filters
                ->where('search', '')
                ->where('sort', 'name')
                ->where('direction', 'asc')
            )
        );
});

test('the contact index reflects the requested sort in the filters prop', function () {
    $campaign = Campaign::factory()->create();
    $viewer = contactPageMember($campaign, Role::Viewer);

    $this->actingAs($viewer)
        ->get(route('campaigns.contacts.index', [$campaign, 'sort' => 'email', 'direction' => 'desc']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Contacts/Index')
            ->where('filters.sort', 'email')
            ->where('filters.direction', 'desc')
        );
});

test('the contact index paginates at fifteen per page', function () {
    $campaign = Campaign::factory()->create();
    $viewer = contactPageMember($campaign, Role::Viewer);
    Contact::factory()->for($campaign)->count(20)->create();

    $this->actingAs($viewer)
        ->get(route('campaigns.contacts.index', $campaign))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('contacts.data', 15)
            ->where('contacts.total', 20)
        );
});
