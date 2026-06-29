<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\PreviewSegmentRequest;
use App\Http\Requests\StoreSegmentRequest;
use App\Http\Requests\UpdateSegmentRequest;
use App\Http\Resources\ContactResource;
use App\Http\Resources\SegmentResource;
use App\Models\Campaign;
use App\Models\Segment;
use App\Segments\SegmentEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Full CRUD plus criteria preview for a campaign's segments over the v1 API.
 *
 * As with the contact endpoints, tenant membership and scoping are enforced by
 * the campaign.access middleware and the route-scoped binding, while per-action
 * authorization and the criteria schema are delegated to the reused
 * SegmentPolicy and Store/Update/Preview segment requests (the latter share the
 * SegmentCriteriaRules trait), so nothing off-schema can be stored or previewed.
 */
class SegmentController extends Controller
{
    /**
     * The most matched contacts a preview returns; the count is always exact.
     */
    protected const PREVIEW_LIMIT = 50;

    /**
     * List the campaign's segments, paginated.
     */
    public function index(Request $request, Campaign $campaign): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Segment::class, $campaign]);

        $segments = $campaign->segments()
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return SegmentResource::collection($segments);
    }

    /**
     * Add a segment to the campaign.
     *
     * The segment is created through the campaign relationship, so campaign_id is
     * always the route campaign and never taken from request input.
     */
    public function store(StoreSegmentRequest $request, Campaign $campaign): JsonResponse
    {
        $segment = $campaign->segments()->create($request->validated());

        return (new SegmentResource($segment))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a segment within the campaign.
     */
    public function update(UpdateSegmentRequest $request, Campaign $campaign, Segment $segment): SegmentResource
    {
        $segment->update($request->validated());

        return new SegmentResource($segment);
    }

    /**
     * Remove a segment from the campaign.
     */
    public function destroy(Request $request, Campaign $campaign, Segment $segment): Response
    {
        $this->authorize('delete', $segment);

        $segment->delete();

        return response()->noContent();
    }

    /**
     * Preview the contacts matched by a (possibly unsaved) set of criteria.
     *
     * The posted criteria are validated against the schema, then run through the
     * evaluator over the campaign's contacts. The count is exact; the contact
     * list is capped at {@see self::PREVIEW_LIMIT} so a broad filter can't dump
     * the whole book. Authorization (ViewContent) lives on the form request.
     */
    public function preview(PreviewSegmentRequest $request, Campaign $campaign, SegmentEvaluator $evaluator): JsonResponse
    {
        $matched = $evaluator->apply($campaign->contacts(), $request->validated('criteria'));

        $sample = (clone $matched)
            ->orderBy('name')
            ->limit(self::PREVIEW_LIMIT)
            ->get();

        return response()->json([
            'data' => [
                'count' => (clone $matched)->count(),
                'contacts' => ContactResource::collection($sample),
            ],
        ]);
    }
}
