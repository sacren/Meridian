<?php

use App\Enums\BlastStatus;
use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The seeder is a manual, dev-only volume tool; the suite exercises its shape at a
 * tiny count, never at full N.
 */
test('it seeds a dedicated benchmark campaign with the full shape', function () {
    $this->artisan('bench:seed-contacts', ['--count' => 20])->assertSuccessful();

    $campaign = Campaign::query()->where('slug', 'like', 'benchmark-%')->sole();

    // Contacts: exactly the requested count, all tenant-scoped.
    expect($campaign->contacts()->count())->toBe(20)
        ->and(Contact::query()->where('campaign_id', '!=', $campaign->id)->count())->toBe(0);

    // Segments: the handful of realistic-ratio filters.
    expect($campaign->segments()->count())->toBe(4);

    // Blasts: a few sent blasts to aggregate over.
    expect($campaign->blasts()->count())->toBe(3)
        ->and($campaign->blasts()->where('status', '!=', BlastStatus::Sent)->count())->toBe(0);

    // Recipients + events: present and tenant-consistent.
    $blastIds = $campaign->blasts()->pluck('id');

    expect(DB::table('blast_recipients')->whereIn('blast_id', $blastIds)->count())->toBeGreaterThan(0)
        ->and(DB::table('email_events')->where('campaign_id', $campaign->id)->count())->toBeGreaterThan(0)
        ->and(DB::table('email_events')->where('campaign_id', '!=', $campaign->id)->count())->toBe(0);
});

test('it is re-runnable, clearing the prior benchmark campaign rather than accumulating', function () {
    $this->artisan('bench:seed-contacts', ['--count' => 15])->assertSuccessful();
    $this->artisan('bench:seed-contacts', ['--count' => 15])->assertSuccessful();

    expect(Campaign::query()->where('slug', 'like', 'benchmark-%')->count())->toBe(1)
        ->and(Contact::query()->count())->toBe(15);
});

test('it rejects a non-positive count', function () {
    $this->artisan('bench:seed-contacts', ['--count' => 0])->assertFailed();

    expect(Campaign::query()->where('slug', 'like', 'benchmark-%')->count())->toBe(0);
});

test('it refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('bench:seed-contacts', ['--count' => 20])->assertFailed();

    expect(Campaign::query()->where('slug', 'like', 'benchmark-%')->count())->toBe(0)
        ->and(Contact::query()->count())->toBe(0);
});
