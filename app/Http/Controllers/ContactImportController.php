<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactImportRequest;
use App\Jobs\ImportContacts;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;

/**
 * Accepts a CSV upload and kicks off an async import — the import half of the
 * Storage stage's CSV contact I/O. Gated by ManageContent (via the form
 * request), scoped to the route campaign by the campaign.access middleware.
 */
class ContactImportController extends Controller
{
    /**
     * Store the validated upload and dispatch the queued import.
     *
     * The file is stored on the object store under a campaign-scoped path — the
     * campaign always comes from the route, never from input — and a
     * contact_imports record is created to track the run. The ImportContacts
     * job is dispatched only after that record is persisted, so the queue worker
     * (a separate process) never loads a missing record.
     */
    public function store(StoreContactImportRequest $request, Campaign $campaign): RedirectResponse
    {
        $path = $request->file('file')->store("imports/{$campaign->id}", 's3');

        $import = $campaign->contactImports()->create([
            'disk' => 's3',
            'path' => $path,
        ]);

        ImportContacts::dispatch($import);

        return to_route('campaigns.contacts.index', $campaign);
    }
}
