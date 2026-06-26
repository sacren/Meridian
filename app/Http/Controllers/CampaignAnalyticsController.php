<?php

namespace App\Http\Controllers;

use App\Analytics\CampaignAnalytics;
use App\Enums\Capability;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignAnalyticsController extends Controller
{
    /**
     * Show a campaign's analytics: per-blast delivery and engagement metrics
     * rolled up alongside campaign-wide totals.
     *
     * Membership is enforced by the `campaign.access` middleware; this additionally
     * requires the ViewContent capability so the gate is explicit and survives a
     * future role that a member could hold without it.
     */
    public function index(Request $request, Campaign $campaign, CampaignAnalytics $analytics): Response
    {
        abort_unless(
            $request->user()?->roleIn($campaign)?->can(Capability::ViewContent) ?? false,
            403,
        );

        return Inertia::render('Campaigns/Analytics', [
            'campaign' => $campaign->only(['id', 'name', 'slug']),
            'analytics' => $analytics->forCampaign($campaign),
        ]);
    }
}
