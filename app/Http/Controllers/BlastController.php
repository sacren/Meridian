<?php

namespace App\Http\Controllers;

use App\Enums\BlastStatus;
use App\Enums\Capability;
use App\Enums\DeliveryStatus;
use App\Http\Requests\StoreBlastRequest;
use App\Http\Requests\UpdateBlastRequest;
use App\Jobs\SendBlastRecipient;
use App\Models\Blast;
use App\Models\BlastRecipient;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Segment;
use App\Segments\SegmentEvaluator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
            'canManageContent' => $request->user()?->roleIn($campaign)?->can(Capability::ManageContent) ?? false,
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
     * Send the blast to its target segment's audience.
     *
     * Sending is send-once and guarded twice over: the policy's `send` gate
     * enforces ManageContent and a still-Draft status, and the action additionally
     * requires a target segment. The segment's audience is resolved through the
     * {@see SegmentEvaluator} against the campaign's contacts; one pending delivery
     * row is written per recipient and the blast is moved to Sending, all in a
     * transaction. Only once that has committed is a {@see SendBlastRecipient} job
     * dispatched per row, so no job can run before its delivery exists.
     */
    public function send(Request $request, Campaign $campaign, Blast $blast, SegmentEvaluator $evaluator): RedirectResponse
    {
        $this->authorize('send', $blast);

        if ($blast->segment === null) {
            return back()->with('error', 'Choose a target segment before sending this blast.');
        }

        $audience = $evaluator->apply(
            $campaign->contacts(),
            $blast->segment->criteria,
        )->get();

        $recipients = DB::transaction(function () use ($blast, $audience): array {
            $rows = $audience
                ->map(fn (Contact $contact): BlastRecipient => $blast->recipients()->create([
                    'contact_id' => $contact->id,
                    'status' => DeliveryStatus::Pending,
                ]))
                ->all();

            $blast->update(['status' => BlastStatus::Sending]);

            return $rows;
        });

        foreach ($recipients as $recipient) {
            SendBlastRecipient::dispatch($recipient);
        }

        return to_route('campaigns.blasts.index', $campaign);
    }

    /**
     * The campaign's blasts, paginated and shaped for the index prop.
     *
     * The target segment is eager-loaded so each row can name it without an N+1,
     * and the recipient totals are counted alongside — the whole audience and the
     * subset the provider has accepted — so a row can show delivery progress
     * (e.g. "12/15 sent") without loading the delivery rows themselves.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginatedBlasts(Campaign $campaign)
    {
        return $campaign->blasts()
            ->with('segment:id,name')
            ->withCount([
                'recipients',
                'recipients as sent_recipients_count' => fn ($query) => $query->where('status', DeliveryStatus::Sent),
            ])
            ->latest()
            ->paginate(15)
            ->through(fn (Blast $blast): array => [
                'id' => $blast->id,
                'subject' => $blast->subject,
                'body' => $blast->body,
                'status' => $blast->status->value,
                'segment_id' => $blast->segment_id,
                'segment' => $blast->segment?->only(['id', 'name']),
                'recipients_count' => $blast->recipients_count,
                'sent_recipients_count' => $blast->sent_recipients_count,
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
