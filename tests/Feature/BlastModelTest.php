<?php

use App\Enums\BlastStatus;
use App\Models\Blast;
use App\Models\Campaign;
use App\Models\Segment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the factory persists a draft blast tied to a campaign and a segment', function () {
    $blast = Blast::factory()->create();

    $this->assertDatabaseHas('blasts', [
        'id' => $blast->id,
        'campaign_id' => $blast->campaign_id,
        'subject' => $blast->subject,
        'body' => $blast->body,
        'status' => 'draft',
        'segment_id' => $blast->segment_id,
    ]);

    expect($blast->campaign_id)->not->toBeNull()
        ->and($blast->segment_id)->not->toBeNull();
});

test('a blast belongs to its campaign and the campaign has many blasts', function () {
    $campaign = Campaign::factory()->create();
    $blast = Blast::factory()->for($campaign)->create();

    expect($blast->campaign)->toBeInstanceOf(Campaign::class)
        ->and($blast->campaign->is($campaign))->toBeTrue()
        ->and($campaign->blasts)->toHaveCount(1)
        ->and($campaign->blasts->first()->is($blast))->toBeTrue();
});

test('a blast belongs to its target segment within the same campaign', function () {
    $blast = Blast::factory()->create();

    expect($blast->segment)->toBeInstanceOf(Segment::class)
        ->and($blast->segment->id)->toBe($blast->segment_id)
        ->and($blast->segment->campaign_id)->toBe($blast->campaign_id);
});

test('status is cast to the BlastStatus enum', function () {
    $blast = Blast::factory()->create();

    expect($blast->status)->toBe(BlastStatus::Draft)
        ->and($blast->fresh()->status)->toBe(BlastStatus::Draft);
});

test('the target segment is nullable and is nulled when the segment is deleted', function () {
    $blast = Blast::factory()->create();
    $segment = $blast->segment;

    $segment->delete();

    expect($blast->fresh()->segment_id)->toBeNull();
});

test('only subject, body, status and segment_id are mass assignable', function () {
    expect((new Blast)->getFillable())->toBe(['subject', 'body', 'status', 'segment_id']);
});
