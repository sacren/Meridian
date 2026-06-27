<?php

use App\Enums\Role;
use App\Jobs\ImportContacts;
use App\Models\Campaign;
use App\Models\ContactImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    Queue::fake();
});

/**
 * Attach $user to $campaign with the given role, returning the user.
 */
function contactImportActionMember(Campaign $campaign, Role $role): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $user;
}

/**
 * A fake CSV upload with the standard header and one row.
 */
function contactImportActionUpload(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'contacts.csv',
        "name,email,phone,custom_fields\nAda Lovelace,ada@example.com,555-0100,\n",
    );
}

test('a staffer upload stores the file, creates one record and dispatches one job', function () {
    $campaign = Campaign::factory()->create();
    $staffer = contactImportActionMember($campaign, Role::Staffer);

    $this->actingAs($staffer)
        ->post(route('campaigns.contacts.import', $campaign), ['file' => contactImportActionUpload()])
        ->assertRedirect(route('campaigns.contacts.index', $campaign));

    expect($campaign->contactImports()->count())->toBe(1);

    $import = $campaign->contactImports()->first();
    expect($import->disk)->toBe('s3');
    Storage::disk('s3')->assertExists($import->path);

    Queue::assertPushed(ImportContacts::class, 1);
});

test('the stored file lives under a campaign-scoped path taken from the route', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();
    $staffer = contactImportActionMember($campaign, Role::Staffer);

    $this->actingAs($staffer)
        ->post(route('campaigns.contacts.import', $campaign), [
            'campaign_id' => $other->id,
            'file' => contactImportActionUpload(),
        ]);

    $import = ContactImport::sole();

    expect($import->campaign_id)->toBe($campaign->id)
        ->and($import->path)->toStartWith("imports/{$campaign->id}/");
});

test('a viewer may not import', function () {
    $campaign = Campaign::factory()->create();
    $viewer = contactImportActionMember($campaign, Role::Viewer);

    $this->actingAs($viewer)
        ->post(route('campaigns.contacts.import', $campaign), ['file' => contactImportActionUpload()])
        ->assertForbidden();

    expect(ContactImport::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a non-member may not import', function () {
    $campaign = Campaign::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->post(route('campaigns.contacts.import', $campaign), ['file' => contactImportActionUpload()])
        ->assertForbidden();

    expect(ContactImport::count())->toBe(0);
});

test('a disallowed file type is rejected', function () {
    $campaign = Campaign::factory()->create();
    $staffer = contactImportActionMember($campaign, Role::Staffer);

    $this->actingAs($staffer)
        ->from(route('campaigns.contacts.index', $campaign))
        ->post(route('campaigns.contacts.import', $campaign), [
            'file' => UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg'),
        ])
        ->assertInvalid(['file']);

    expect(ContactImport::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('an oversize file is rejected', function () {
    $campaign = Campaign::factory()->create();
    $staffer = contactImportActionMember($campaign, Role::Staffer);

    $this->actingAs($staffer)
        ->from(route('campaigns.contacts.index', $campaign))
        ->post(route('campaigns.contacts.import', $campaign), [
            'file' => UploadedFile::fake()->create('big.csv', 6000),
        ])
        ->assertInvalid(['file']);

    expect(ContactImport::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('the file is required', function () {
    $campaign = Campaign::factory()->create();
    $staffer = contactImportActionMember($campaign, Role::Staffer);

    $this->actingAs($staffer)
        ->from(route('campaigns.contacts.index', $campaign))
        ->post(route('campaigns.contacts.import', $campaign), [])
        ->assertInvalid(['file']);

    expect(ContactImport::count())->toBe(0);
});
