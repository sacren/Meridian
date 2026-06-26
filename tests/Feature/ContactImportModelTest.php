<?php

use App\Enums\ImportStatus;
use App\Models\Campaign;
use App\Models\ContactImport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the factory persists a pending import tied to a campaign', function () {
    $import = ContactImport::factory()->create();

    $this->assertDatabaseHas('contact_imports', [
        'id' => $import->id,
        'campaign_id' => $import->campaign_id,
        'disk' => $import->disk,
        'path' => $import->path,
        'status' => ImportStatus::Pending->value,
        'imported_count' => 0,
        'failed_count' => 0,
    ]);

    expect($import->campaign_id)->not->toBeNull();
});

test('an import belongs to its campaign and the campaign has many imports', function () {
    $campaign = Campaign::factory()->create();
    $import = ContactImport::factory()->for($campaign)->create();

    expect($import->campaign)->toBeInstanceOf(Campaign::class)
        ->and($import->campaign->is($campaign))->toBeTrue()
        ->and($campaign->contactImports)->toHaveCount(1)
        ->and($campaign->contactImports->first()->is($import))->toBeTrue();
});

test('the status is cast to the ImportStatus enum', function () {
    $import = ContactImport::factory()->completed()->create();

    expect($import->fresh()->status)->toBe(ImportStatus::Completed);
});

test('the errors report round-trips as an array', function () {
    $report = [
        ['row' => 2, 'reason' => 'The email is not a valid address.'],
        ['row' => 5, 'reason' => 'The custom_fields cell is not valid JSON.'],
    ];

    $import = ContactImport::factory()->create(['errors' => $report]);

    expect($import->fresh()->errors)
        ->toBeArray()
        ->toEqual($report);
});

test('completed_at is cast to a datetime', function () {
    $import = ContactImport::factory()->completed()->create();

    expect($import->fresh()->completed_at)->toBeInstanceOf(DateTimeInterface::class);
});

test('deleting the campaign cascades to its imports', function () {
    $campaign = Campaign::factory()->create();
    $import = ContactImport::factory()->for($campaign)->create();

    $campaign->delete();

    $this->assertDatabaseMissing('contact_imports', ['id' => $import->id]);
});
