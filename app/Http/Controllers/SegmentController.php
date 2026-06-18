<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewSegmentRequest;
use App\Http\Requests\StoreSegmentRequest;
use App\Http\Requests\UpdateSegmentRequest;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Segment;
use App\Segments\SegmentEvaluator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SegmentController extends Controller
{
    use AuthorizesRequests;

    /**
     * The most matched contacts a preview returns; the count is always exact.
     */
    protected const PREVIEW_LIMIT = 50;

    /**
     * List the campaign's segments.
     */
    public function index(Request $request, Campaign $campaign): Response
    {
        $this->authorize('viewAny', [Segment::class, $campaign]);

        return Inertia::render('Segments/Index', [
            'campaign' => $campaign->only(['id', 'name', 'slug']),
            'segments' => $this->paginatedSegments($campaign),
        ]);
    }

    /**
     * Add a segment to the campaign.
     *
     * The segment is created through the campaign relationship, so campaign_id is
     * always the route campaign and never taken from request input.
     */
    public function store(StoreSegmentRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->segments()->create($request->validated());

        return to_route('campaigns.segments.index', $campaign);
    }

    /**
     * Update a segment within the campaign.
     */
    public function update(UpdateSegmentRequest $request, Campaign $campaign, Segment $segment): RedirectResponse
    {
        $segment->update($request->validated());

        return to_route('campaigns.segments.index', $campaign);
    }

    /**
     * Remove a segment from the campaign.
     */
    public function destroy(Request $request, Campaign $campaign, Segment $segment): RedirectResponse
    {
        $this->authorize('delete', $segment);

        $segment->delete();

        return to_route('campaigns.segments.index', $campaign);
    }

    /**
     * Preview the contacts matched by a (possibly unsaved) set of criteria.
     *
     * Powers the builder's live preview: the posted criteria are validated against
     * the schema, then run through the evaluator over the campaign's contacts. The
     * count is exact; the contact list is capped at {@see self::PREVIEW_LIMIT} so a
     * broad filter can't dump the whole book. Authorization (ViewContent) lives on
     * the form request.
     */
    public function preview(PreviewSegmentRequest $request, Campaign $campaign, SegmentEvaluator $evaluator): Response
    {
        $matches = $evaluator->evaluate(
            $request->validated('criteria'),
            $campaign->contacts()->orderBy('name')->get(),
        );

        return Inertia::render('Segments/Index', [
            'campaign' => $campaign->only(['id', 'name', 'slug']),
            'segments' => $this->paginatedSegments($campaign),
            'preview' => [
                'count' => $matches->count(),
                'contacts' => $matches
                    ->take(self::PREVIEW_LIMIT)
                    ->map(fn (Contact $contact): array => [
                        'id' => $contact->id,
                        'name' => $contact->name,
                        'email' => $contact->email,
                        'phone' => $contact->phone,
                    ])
                    ->values(),
            ],
        ]);
    }

    /**
     * The campaign's segments, paginated and shaped for the index prop.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginatedSegments(Campaign $campaign)
    {
        return $campaign->segments()
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (Segment $segment): array => [
                'id' => $segment->id,
                'name' => $segment->name,
                'criteria' => $segment->criteria,
            ]);
    }
}
