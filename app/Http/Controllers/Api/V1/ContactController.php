<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Full CRUD for a campaign's contacts over the v1 API.
 *
 * Tenant membership is enforced by the campaign.access middleware and the
 * route-scoped binding (a {contact} must belong to {campaign}); per-action
 * authorization and validation are delegated to the reused ContactPolicy and
 * Store/UpdateContactRequest, so this controller carries no business rules of
 * its own beyond shaping the JSON response.
 */
class ContactController extends Controller
{
    /**
     * The contact columns a client may sort by.
     *
     * User-supplied sort input is matched against this whitelist so a request can
     * never order by an arbitrary column.
     *
     * @var list<string>
     */
    protected array $sortable = ['name', 'email', 'created_at'];

    /**
     * List the campaign's contacts, paginated and optionally searched/sorted.
     */
    public function index(Request $request, Campaign $campaign): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Contact::class, $campaign]);

        $search = trim($request->string('search')->value());
        $sort = $request->string('sort')->value();
        $direction = $request->string('direction')->lower()->value() === 'desc' ? 'desc' : 'asc';

        $sortColumn = in_array($sort, $this->sortable, true) ? $sort : 'name';

        $contacts = $campaign->contacts()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortColumn, $direction)
            ->paginate(15)
            ->withQueryString();

        return ContactResource::collection($contacts);
    }

    /**
     * Add a contact to the campaign.
     *
     * The contact is created through the campaign relationship, so campaign_id is
     * always the route campaign and never taken from request input.
     */
    public function store(StoreContactRequest $request, Campaign $campaign): JsonResponse
    {
        $contact = $campaign->contacts()->create($request->validated());

        return (new ContactResource($contact))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a contact within the campaign.
     */
    public function update(UpdateContactRequest $request, Campaign $campaign, Contact $contact): ContactResource
    {
        $contact->update($request->validated());

        return new ContactResource($contact);
    }

    /**
     * Remove a contact from the campaign.
     */
    public function destroy(Request $request, Campaign $campaign, Contact $contact): Response
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return response()->noContent();
    }
}
