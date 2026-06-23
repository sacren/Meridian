<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only campaign endpoints for the v1 API.
 */
class CampaignController extends Controller
{
    /**
     * List the campaigns the authenticated user belongs to.
     *
     * Scoped to the caller's own memberships, so no campaign.access gate is
     * needed; the pivot is loaded, exposing each membership's role.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $campaigns = $request->user()
            ->campaigns()
            ->orderBy('name')
            ->get();

        return CampaignResource::collection($campaigns);
    }

    /**
     * Show a single campaign the authenticated user is a member of.
     *
     * Membership is enforced by the campaign.access middleware on the route
     * (403 for a non-member, 404 for an unknown slug); the bound model carries
     * no pivot, so the caller's role is omitted from the response.
     */
    public function show(Campaign $campaign): CampaignResource
    {
        return new CampaignResource($campaign);
    }
}
