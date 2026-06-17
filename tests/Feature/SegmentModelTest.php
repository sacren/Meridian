<?php

use App\Models\Campaign;
use App\Models\Segment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the factory persists a segment tied to a campaign', function () {
    $segment = Segment::factory()->create();

    $this->assertDatabaseHas('segments', [
        'id' => $segment->id,
        'campaign_id' => $segment->campaign_id,
        'name' => $segment->name,
    ]);

    expect($segment->campaign_id)->not->toBeNull();
});

test('a segment belongs to its campaign and the campaign has many segments', function () {
    $campaign = Campaign::factory()->create();
    $segment = Segment::factory()->for($campaign)->create();

    expect($segment->campaign)->toBeInstanceOf(Campaign::class)
        ->and($segment->campaign->is($campaign))->toBeTrue()
        ->and($campaign->segments)->toHaveCount(1)
        ->and($campaign->segments->first()->is($segment))->toBeTrue();
});

test('criteria is cast to an array', function () {
    $criteria = [
        'combinator' => 'and',
        'rules' => [
            ['field' => 'email', 'operator' => 'contains', 'value' => '@example.com'],
        ],
    ];

    $segment = Segment::factory()->create(['criteria' => $criteria]);

    expect($segment->fresh()->criteria)->toEqualCanonicalizing($criteria);
});

test('only name and criteria are mass assignable', function () {
    expect((new Segment)->getFillable())->toBe(['name', 'criteria']);
});
