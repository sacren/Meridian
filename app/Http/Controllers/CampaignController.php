<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreCampaignRequest;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    /**
     * Create a campaign and land the creator inside it as its owner.
     *
     * The campaign and its sole owner pivot row are written in one transaction, so the
     * "creator → owner" contract holds atomically: no campaign is ever left ownerless.
     */
    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $name = $request->validated('name');

        $campaign = DB::transaction(function () use ($request, $name) {
            $campaign = Campaign::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
            ]);

            $campaign->users()->attach($request->user(), ['role' => Role::Owner->value]);

            return $campaign;
        });

        return to_route('campaigns.dashboard', $campaign);
    }

    /**
     * Show a campaign's dashboard, the landing surface inside the tenant boundary.
     */
    public function dashboard(Request $request, Campaign $campaign): Response
    {
        return Inertia::render('Campaigns/Dashboard', [
            'campaign' => $campaign->only(['id', 'name', 'slug']),
            'role' => $request->attributes->get('role')->value,
        ]);
    }

    /**
     * Build a campaign slug from its name that is unique across campaigns,
     * appending an incrementing suffix on collision.
     */
    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'campaign';
        $slug = $base;
        $suffix = 2;

        while (Campaign::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
