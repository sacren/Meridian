<?php

use App\Analytics\CampaignAnalytics;
use App\Enums\DeliveryStatus;
use App\Enums\EmailEventType;
use App\Models\Blast;
use App\Models\BlastRecipient;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\EmailEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Seed one blast with a random spread of recipient statuses and event types.
 * Each of the three delivery statuses and three event types gets a random count
 * that may be zero, so blasts routinely land with no recipients, no events, or
 * neither — exactly the "missing from the aggregate" rows {@see
 * CampaignAnalytics::forCampaign()} must default to zero.
 */
function seedBlastVolume(Campaign $campaign): void
{
    $blast = Blast::factory()->for($campaign)->create([
        'status' => fake()->randomElement(['draft', 'sending', 'sent', 'failed']),
    ]);

    foreach ([DeliveryStatus::Pending, DeliveryStatus::Sent, DeliveryStatus::Failed] as $status) {
        $count = fake()->numberBetween(0, 10);

        if ($count > 0) {
            BlastRecipient::factory()->for($blast)->count($count)->create(['status' => $status]);
        }
    }

    foreach ([EmailEventType::Open, EmailEventType::Click, EmailEventType::Bounce] as $type) {
        $count = fake()->numberBetween(0, 10);

        if ($count > 0) {
            $contact = Contact::factory()->for($campaign)->create();

            EmailEvent::factory()->count($count)->create([
                'campaign_id' => $campaign->id,
                'blast_id' => $blast->id,
                'contact_id' => $contact->id,
                'type' => $type,
            ]);
        }
    }
}

test('the aggregate-SQL rollup equals the in-memory oracle across randomized campaign volumes', function () {
    $analytics = app(CampaignAnalytics::class);

    $campaign = Campaign::factory()->create();

    foreach (range(1, fake()->numberBetween(4, 7)) as $ignored) {
        seedBlastVolume($campaign);
    }

    // A blast with no deliveries and no events at all: absent from both aggregates.
    Blast::factory()->for($campaign)->create();

    $sql = $analytics->forCampaign($campaign);
    $oracle = $analytics->forCampaignInMemory($campaign);

    // Totals match exactly, including int vs. float types (SUM/COUNT come back as
    // strings from MySQL and must be cast).
    expect($sql['totals'])->toBe($oracle['totals']);

    // Every per-blast row matches exactly. Keyed by id and sorted so the value
    // comparison does not depend on row order.
    $sqlById = collect($sql['blasts'])->keyBy('id')->all();
    $oracleById = collect($oracle['blasts'])->keyBy('id')->all();
    ksort($sqlById);
    ksort($oracleById);

    expect($sqlById)->toBe($oracleById);

    // The latest() ordering is preserved identically by both paths.
    expect(array_column($sql['blasts'], 'id'))->toBe(array_column($oracle['blasts'], 'id'));
})->repeat(5);

test('a campaign with no blasts yields the same zeroed read model from both paths', function () {
    $analytics = app(CampaignAnalytics::class);
    $campaign = Campaign::factory()->create();

    $sql = $analytics->forCampaign($campaign);

    expect($sql)->toBe($analytics->forCampaignInMemory($campaign))
        ->and($sql['blasts'])->toBe([])
        ->and($sql['totals']['recipients'])->toBe(0)
        ->and($sql['totals']['open_rate'])->toBe(0.0);
});
