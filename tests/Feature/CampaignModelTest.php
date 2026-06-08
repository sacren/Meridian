<?php

use App\Models\Campaign;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the factory persists a campaign with a unique slug', function () {
    $first = Campaign::factory()->create();
    $second = Campaign::factory()->create();

    expect($first->slug)->not->toBe($second->slug);

    $this->assertDatabaseHas('campaigns', [
        'id' => $first->id,
        'name' => $first->name,
        'slug' => $first->slug,
    ]);
});

test('the slug column is unique', function () {
    Campaign::factory()->create(['slug' => 'acme-campaign']);

    Campaign::factory()->create(['slug' => 'acme-campaign']);
})->throws(QueryException::class);

test('only name and slug are mass assignable', function () {
    expect((new Campaign)->getFillable())->toBe(['name', 'slug']);
});
