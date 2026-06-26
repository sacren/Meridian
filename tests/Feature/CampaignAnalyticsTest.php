<?php

use App\Analytics\CampaignAnalytics;
use App\Enums\EmailEventType;
use App\Enums\Role;
use App\Models\Blast;
use App\Models\BlastRecipient;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\EmailEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * Attach $user to $campaign with the given role, returning the user.
 */
function analyticsMember(Campaign $campaign, Role $role): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $user;
}

/**
 * Record $count events of $type against $blast, tenant-consistent with its campaign.
 */
function analyticsEvents(Blast $blast, EmailEventType $type, int $count): void
{
    $contact = Contact::factory()->for($blast->campaign)->create();

    EmailEvent::factory()->count($count)->create([
        'campaign_id' => $blast->campaign_id,
        'blast_id' => $blast->id,
        'contact_id' => $contact->id,
        'type' => $type,
    ]);
}

test('the rollup counts deliveries and events into per-blast metrics and rates', function () {
    $campaign = Campaign::factory()->create();
    $blast = Blast::factory()->for($campaign)->create(['status' => 'sent']);
    BlastRecipient::factory()->for($blast)->count(3)->sent()->create();
    BlastRecipient::factory()->for($blast)->failed()->create();
    analyticsEvents($blast, EmailEventType::Open, 2);
    analyticsEvents($blast, EmailEventType::Click, 1);
    analyticsEvents($blast, EmailEventType::Bounce, 1);

    $analytics = app(CampaignAnalytics::class)->forCampaign($campaign);

    expect($analytics['blasts'])->toHaveCount(1);
    expect($analytics['blasts'][0])->toMatchArray([
        'id' => $blast->id,
        'subject' => $blast->subject,
        'status' => 'sent',
        'recipients' => 4,
        'sent' => 3,
        'failed' => 1,
        'opens' => 2,
        'clicks' => 1,
        'bounces' => 1,
        'open_rate' => 66.7,   // 2 of 3 delivered
        'click_rate' => 33.3,  // 1 of 3 delivered
        'bounce_rate' => 25.0, // 1 of 4 recipients
    ]);
});

test('the campaign totals sum the blasts and derive rates from the summed counts', function () {
    $campaign = Campaign::factory()->create();

    $first = Blast::factory()->for($campaign)->create(['status' => 'sent']);
    BlastRecipient::factory()->for($first)->count(2)->sent()->create();
    analyticsEvents($first, EmailEventType::Open, 1);

    $second = Blast::factory()->for($campaign)->create(['status' => 'sent']);
    BlastRecipient::factory()->for($second)->count(2)->sent()->create();
    analyticsEvents($second, EmailEventType::Open, 3);

    $totals = app(CampaignAnalytics::class)->forCampaign($campaign)['totals'];

    expect($totals)->toMatchArray([
        'recipients' => 4,
        'sent' => 4,
        'opens' => 4,
        'open_rate' => 100.0, // 4 opens over 4 delivered, not the mean of 50% and 150%
    ]);
});

test('a blast with no deliveries reports zero rates rather than dividing by zero', function () {
    $campaign = Campaign::factory()->create();
    Blast::factory()->for($campaign)->create();

    $metrics = app(CampaignAnalytics::class)->forCampaign($campaign)['blasts'][0];

    expect($metrics['open_rate'])->toBe(0.0)
        ->and($metrics['bounce_rate'])->toBe(0.0)
        ->and($metrics['recipients'])->toBe(0);
});

test('the analytics page renders with the totals and per-blast prop shape', function () {
    $campaign = Campaign::factory()->create();
    $viewer = analyticsMember($campaign, Role::Viewer);
    $blast = Blast::factory()->for($campaign)->create(['status' => 'sent']);
    BlastRecipient::factory()->for($blast)->count(2)->sent()->create();
    analyticsEvents($blast, EmailEventType::Open, 1);

    $this->actingAs($viewer)
        ->get(route('campaigns.analytics.index', $campaign))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Campaigns/Analytics')
            ->where('campaign.slug', $campaign->slug)
            ->has('analytics.totals', fn (AssertableInertia $totals) => $totals
                ->where('recipients', 2)
                ->where('sent', 2)
                ->where('opens', 1)
                ->has('open_rate')
                ->etc()
            )
            ->has('analytics.blasts', 1, fn (AssertableInertia $metrics) => $metrics
                ->where('id', $blast->id)
                ->where('recipients', 2)
                ->where('opens', 1)
                ->etc()
            )
        );
});

test('a viewer may view the analytics but a non-member may not', function () {
    $campaign = Campaign::factory()->create();

    $this->actingAs(analyticsMember($campaign, Role::Viewer))
        ->get(route('campaigns.analytics.index', $campaign))
        ->assertOk();

    $this->actingAs(User::factory()->create())
        ->get(route('campaigns.analytics.index', $campaign))
        ->assertForbidden();
});
