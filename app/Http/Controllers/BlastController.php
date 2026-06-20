<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBlastRequest;
use App\Http\Requests\UpdateBlastRequest;
use App\Models\Blast;
use App\Models\Campaign;
use App\Models\Segment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class BlastController extends Controller
{
    use AuthorizesRequests;

    /**
     * List the campaign's blasts.
     *
     * Alongside the blasts, the campaign's segments are passed so the builder can
     * offer them as targets without a second request.
     */
    public function index(Request $request, Campaign $campaign): Response
    {
        $this->authorize('viewAny', [Blast::class, $campaign]);

        return Inertia::render('Blasts/Index', [
            'campaign' => $campaign->only(['id', 'name', 'slug']),
            'blasts' => $this->paginatedBlasts($campaign),
            'segments' => $this->targetableSegments($campaign),
        ]);
    }

    /**
     * Add a blast to the campaign.
     *
     * The blast is created through the campaign relationship, so campaign_id is
     * always the route campaign and never taken from request input. The status is
     * left to its 'draft' default — blasts are draft-only in this domain.
     */
    public function store(StoreBlastRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->blasts()->create($request->validated());

        return to_route('campaigns.blasts.index', $campaign);
    }

    /**
     * Update a blast within the campaign.
     */
    public function update(UpdateBlastRequest $request, Campaign $campaign, Blast $blast): RedirectResponse
    {
        $blast->update($request->validated());

        return to_route('campaigns.blasts.index', $campaign);
    }

    /**
     * Remove a blast from the campaign.
     */
    public function destroy(Request $request, Campaign $campaign, Blast $blast): RedirectResponse
    {
        $this->authorize('delete', $blast);

        $blast->delete();

        return to_route('campaigns.blasts.index', $campaign);
    }

    /**
     * The campaign's blasts, paginated and shaped for the index prop.
     *
     * The target segment is eager-loaded so each row can name it without an N+1.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginatedBlasts(Campaign $campaign)
    {
        return $campaign->blasts()
            ->with('segment:id,name')
            ->latest()
            ->paginate(15)
            ->through(fn (Blast $blast): array => [
                'id' => $blast->id,
                'subject' => $blast->subject,
                'body' => $blast->body,
                'status' => $blast->status->value,
                'segment_id' => $blast->segment_id,
                'segment' => $blast->segment?->only(['id', 'name']),
            ]);
    }

    /**
     * The campaign's segments offered as blast targets.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function targetableSegments(Campaign $campaign)
    {
        return $campaign->segments()
            ->orderBy('name')
            ->get()
            ->map(fn (Segment $segment): array => [
                'id' => $segment->id,
                'name' => $segment->name,
            ]);
    }
}
