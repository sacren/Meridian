<?php

namespace App\Http\Controllers;

use App\Enums\Capability;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    use AuthorizesRequests;

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
    public function index(Request $request, Campaign $campaign): Response
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
            ->withQueryString()
            ->through(fn (Contact $contact): array => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
            ]);

        return Inertia::render('Contacts/Index', [
            'campaign' => $campaign->only(['id', 'name', 'slug']),
            'contacts' => $contacts,
            'filters' => [
                'search' => $search,
                'sort' => $sortColumn,
                'direction' => $direction,
            ],
            'canManageContent' => $request->user()?->roleIn($campaign)?->can(Capability::ManageContent) ?? false,
            'latestImport' => $this->latestImport($campaign),
        ]);
    }

    /**
     * The campaign's most-recent contact import, shaped for the status surface.
     *
     * Only the fields the surface renders are exposed; null when the campaign has
     * never been imported into. The status enum and completion timestamp are
     * flattened to primitives (a string and an ISO-8601 string) the page consumes.
     *
     * @return array<string, mixed>|null
     */
    protected function latestImport(Campaign $campaign): ?array
    {
        $import = $campaign->contactImports()->latest()->first();

        if ($import === null) {
            return null;
        }

        return [
            'id' => $import->id,
            'status' => $import->status->value,
            'imported_count' => $import->imported_count,
            'failed_count' => $import->failed_count,
            'errors' => $import->errors,
            'completed_at' => $import->completed_at?->toIso8601String(),
        ];
    }

    /**
     * Add a contact to the campaign.
     *
     * The contact is created through the campaign relationship, so campaign_id is
     * always the route campaign and never taken from request input.
     */
    public function store(StoreContactRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->contacts()->create($request->validated());

        return to_route('campaigns.contacts.index', $campaign);
    }

    /**
     * Update a contact within the campaign.
     */
    public function update(UpdateContactRequest $request, Campaign $campaign, Contact $contact): RedirectResponse
    {
        $contact->update($request->validated());

        return to_route('campaigns.contacts.index', $campaign);
    }

    /**
     * Remove a contact from the campaign.
     */
    public function destroy(Request $request, Campaign $campaign, Contact $contact): RedirectResponse
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return to_route('campaigns.contacts.index', $campaign);
    }
}
