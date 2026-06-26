<?php

use App\Enums\ImportStatus;
use App\Jobs\ImportContacts;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

/**
 * Encode rows to CSV exactly as the export does — native fputcsv with an empty
 * escape — so the import is exercised against the real round-trip format.
 *
 * @param  list<list<string>>  $rows
 */
function importContactsCsv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');

    foreach ($rows as $row) {
        fputcsv($handle, $row, escape: '');
    }

    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    return $content;
}

/**
 * Store CSV content on the faked s3 disk and return a pending import for it.
 */
function storeContactImport(Campaign $campaign, string $content): ContactImport
{
    $path = 'imports/'.fake()->uuid().'.csv';
    Storage::disk('s3')->put($path, $content);

    return ContactImport::factory()->for($campaign)->create([
        'disk' => 's3',
        'path' => $path,
    ]);
}

test('the job imports each row as a contact in the campaign', function () {
    $campaign = Campaign::factory()->create();
    $import = storeContactImport($campaign, importContactsCsv([
        ['name', 'email', 'phone', 'custom_fields'],
        ['Ada Lovelace', 'ada@example.com', '555-0100', ''],
        ['Grace Hopper', 'grace@example.com', '', ''],
    ]));

    (new ImportContacts($import))->handle();

    expect($campaign->contacts()->count())->toBe(2);
    $this->assertDatabaseHas('contacts', [
        'campaign_id' => $campaign->id,
        'email' => 'ada@example.com',
        'name' => 'Ada Lovelace',
        'phone' => '555-0100',
    ]);
});

test('a row whose email already exists updates the contact instead of duplicating it', function () {
    $campaign = Campaign::factory()->create();
    Contact::factory()->for($campaign)->create(['email' => 'ada@example.com', 'name' => 'Old Name']);

    $import = storeContactImport($campaign, importContactsCsv([
        ['name', 'email', 'phone', 'custom_fields'],
        ['Ada Lovelace', 'ada@example.com', '555-0100', ''],
    ]));

    (new ImportContacts($import))->handle();

    expect($campaign->contacts()->count())->toBe(1)
        ->and($campaign->contacts()->first()->name)->toBe('Ada Lovelace');
});

test('an uppercase or padded email upserts against the normalized existing contact', function () {
    $campaign = Campaign::factory()->create();
    Contact::factory()->for($campaign)->create(['email' => 'ada@example.com']);

    $import = storeContactImport($campaign, importContactsCsv([
        ['name', 'email', 'phone', 'custom_fields'],
        ['Ada Lovelace', '  ADA@Example.com ', '', ''],
    ]));

    (new ImportContacts($import))->handle();

    expect($campaign->contacts()->count())->toBe(1)
        ->and($campaign->contacts()->first()->email)->toBe('ada@example.com');
});

test('the job writes only into the import campaign', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();

    $import = storeContactImport($campaign, importContactsCsv([
        ['name', 'email', 'phone', 'custom_fields'],
        ['Ada', 'ada@example.com', '', ''],
    ]));

    (new ImportContacts($import))->handle();

    expect($campaign->contacts()->count())->toBe(1)
        ->and($other->contacts()->count())->toBe(0);
});

test('rows with a bad email or malformed custom_fields are reported and skipped', function () {
    $campaign = Campaign::factory()->create();
    $import = storeContactImport($campaign, importContactsCsv([
        ['name', 'email', 'phone', 'custom_fields'],
        ['Valid', 'valid@example.com', '', ''],
        ['Bad Email', 'not-an-email', '', ''],
        ['Bad Json', 'badjson@example.com', '', '{not json}'],
    ]));

    (new ImportContacts($import))->handle();

    expect($campaign->contacts()->count())->toBe(1);
    $this->assertDatabaseHas('contacts', ['campaign_id' => $campaign->id, 'email' => 'valid@example.com']);
    $this->assertDatabaseMissing('contacts', ['email' => 'badjson@example.com']);

    $import->refresh();
    expect($import->imported_count)->toBe(1)
        ->and($import->failed_count)->toBe(2)
        ->and($import->errors)->toHaveCount(2);
});

test('the import settles as completed with counts and a completion time', function () {
    $campaign = Campaign::factory()->create();
    $import = storeContactImport($campaign, importContactsCsv([
        ['name', 'email', 'phone', 'custom_fields'],
        ['Ada', 'ada@example.com', '', ''],
    ]));

    (new ImportContacts($import))->handle();

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->imported_count)->toBe(1)
        ->and($import->failed_count)->toBe(0)
        ->and($import->completed_at)->not->toBeNull();
});

test('a custom_fields JSON cell round-trips from the exported quoted form', function () {
    $campaign = Campaign::factory()->create();
    $import = storeContactImport($campaign, importContactsCsv([
        ['name', 'email', 'phone', 'custom_fields'],
        ['Ada Lovelace', 'ada@example.com', '555-0100', json_encode(['tier' => 'gold'])],
    ]));

    (new ImportContacts($import))->handle();

    $contact = $campaign->contacts()->firstWhere('email', 'ada@example.com');
    expect($contact->custom_fields)->toBe(['tier' => 'gold']);
});

test('re-running the same import re-lands the same state without duplicating', function () {
    $campaign = Campaign::factory()->create();
    $import = storeContactImport($campaign, importContactsCsv([
        ['name', 'email', 'phone', 'custom_fields'],
        ['Ada', 'ada@example.com', '', ''],
        ['Grace', 'grace@example.com', '', ''],
    ]));

    (new ImportContacts($import))->handle();
    (new ImportContacts($import))->handle();

    expect($campaign->contacts()->count())->toBe(2);
});

test('a missing import file settles the record as failed', function () {
    $campaign = Campaign::factory()->create();
    $import = ContactImport::factory()->for($campaign)->create([
        'disk' => 's3',
        'path' => 'imports/does-not-exist.csv',
    ]);

    (new ImportContacts($import))->handle();

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->completed_at)->not->toBeNull()
        ->and($import->errors)->not->toBeNull();
});
