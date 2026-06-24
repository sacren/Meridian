<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreBlastRequest;
use App\Http\Requests\UpdateBlastRequest;
use App\Http\Resources\BlastResource;
use App\Models\Blast;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Full CRUD for a campaign's blasts over the v1 API.
 *
 * As with the other resource controllers, tenant membership and scoping are
 * enforced by the campaign.access middleware and the route-scoped binding,
 * while per-action authorization, validation and the tenant-consistent target
 * rule (a blast's segment_id must belong to the same campaign) are delegated to
 * the reused BlastPolicy and Store/UpdateBlastRequest. A blast stays a draft:
 * status is never accepted as input, so it keeps its database default.
 */
class BlastController extends Controller
{
    /**
     * List the campaign's blasts, paginated.
     *
     * The target segment is eager-loaded so each row can name it without an N+1.
     */
    public function index(Request $request, Campaign $campaign): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Blast::class, $campaign]);

        $blasts = $campaign->blasts()
            ->with('segment:id,name')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return BlastResource::collection($blasts);
    }

    /**
     * Add a blast to the campaign.
     *
     * The blast is created through the campaign relationship, so campaign_id is
     * always the route campaign and never taken from request input.
     */
    public function store(StoreBlastRequest $request, Campaign $campaign): JsonResponse
    {
        $blast = $campaign->blasts()->create($request->validated());

        // The status column's 'draft' default is applied by the database, so the
        // freshly created model has no status in memory; refresh to load it (and
        // the target segment) before serializing.
        $blast->refresh()->load('segment');

        return (new BlastResource($blast))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a blast within the campaign.
     */
    public function update(UpdateBlastRequest $request, Campaign $campaign, Blast $blast): BlastResource
    {
        $blast->update($request->validated());

        return new BlastResource($blast->load('segment'));
    }

    /**
     * Remove a blast from the campaign.
     */
    public function destroy(Request $request, Campaign $campaign, Blast $blast): Response
    {
        $this->authorize('delete', $blast);

        $blast->delete();

        return response()->noContent();
    }
}
