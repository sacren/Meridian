<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Attach $user to $campaign with the given role, returning the user.
 */
function contactExportMember(Campaign $campaign, Role $role): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $user;
}

test('a viewer can export the campaign contacts as a CSV with a header row', function () {
    $campaign = Campaign::factory()->create();
    $viewer = contactExportMember($campaign, Role::Viewer);
    Contact::factory()->for($campaign)->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'phone' => '555-0100',
        'custom_fields' => ['tier' => 'gold'],
    ]);

    $response = $this->actingAs($viewer)->get(route('campaigns.contacts.export', $campaign));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('name,email,phone,custom_fields');
    expect($csv)->toContain('"Ada Lovelace",ada@example.com,555-0100,"{""tier"":""gold""}"');
});

test('the export contains only the route campaign contacts', function () {
    $campaign = Campaign::factory()->create();
    $staffer = contactExportMember($campaign, Role::Staffer);
    Contact::factory()->for($campaign)->create(['email' => 'mine@example.com']);
    Contact::factory()->for(Campaign::factory())->create(['email' => 'other@example.com']);

    $csv = $this->actingAs($staffer)
        ->get(route('campaigns.contacts.export', $campaign))
        ->streamedContent();

    expect($csv)->toContain('mine@example.com');
    expect($csv)->not->toContain('other@example.com');
});

test('a null custom_fields value exports as an empty cell', function () {
    $campaign = Campaign::factory()->create();
    $viewer = contactExportMember($campaign, Role::Viewer);
    Contact::factory()->for($campaign)->create([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'phone' => null,
        'custom_fields' => null,
    ]);

    $csv = $this->actingAs($viewer)
        ->get(route('campaigns.contacts.export', $campaign))
        ->streamedContent();

    expect($csv)->toContain('"Grace Hopper",grace@example.com,,');
});

test('a non-member cannot export the campaign contacts', function () {
    $campaign = Campaign::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('campaigns.contacts.export', $campaign))
        ->assertForbidden();
});
