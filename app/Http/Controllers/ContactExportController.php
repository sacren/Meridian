<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a campaign's contacts out as a CSV download — the export half of the
 * Storage stage's CSV contact I/O. Gated by ViewContent (any member may export),
 * scoped to the route campaign by the campaign.access middleware.
 */
class ContactExportController extends Controller
{
    use AuthorizesRequests;

    /**
     * The CSV header row — the shared column contract native fputcsv/fgetcsv
     * carry across export and import (custom_fields is a JSON-encoded cell).
     *
     * @var list<string>
     */
    protected array $columns = ['name', 'email', 'phone', 'custom_fields'];

    /**
     * Stream the route campaign's contacts as a CSV download.
     *
     * The contacts are pulled through a lazy() cursor and written straight to the
     * output stream with native fputcsv, so even a large contact set never buffers
     * in memory. An empty escape keeps the quoting RFC 4180-correct.
     */
    public function __invoke(Campaign $campaign): StreamedResponse
    {
        $this->authorize('viewAny', [Contact::class, $campaign]);

        return response()->streamDownload(function () use ($campaign): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $this->columns, escape: '');

            $campaign->contacts()->orderBy('name')->lazy()->each(function (Contact $contact) use ($handle): void {
                fputcsv($handle, [
                    $contact->name,
                    $contact->email,
                    $contact->phone,
                    $contact->custom_fields === null ? '' : json_encode($contact->custom_fields),
                ], escape: '');
            });

            fclose($handle);
        }, "{$campaign->slug}-contacts.csv", [
            'Content-Type' => 'text/csv',
        ]);
    }
}
