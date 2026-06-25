<?php

namespace App\Http\Controllers;

use App\Enums\EmailEventType;
use App\Http\Middleware\VerifyEmailWebhookSignature;
use App\Models\BlastRecipient;
use App\Models\EmailEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ingests inbound provider webhooks into {@see EmailEvent}s.
 *
 * The signature is already verified by {@see VerifyEmailWebhookSignature},
 * so this action only normalizes and attributes the payload. It is idempotent
 * (a replayed provider event id is deduped) and forgiving (a payload whose
 * message id matches no delivery is accepted and dropped, never a 500), so a
 * provider's retries and out-of-band events cannot break ingestion.
 */
class EmailWebhookController extends Controller
{
    /**
     * Record a normalized email event, correlating it to a delivery by message id.
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'event_id' => ['required', 'string'],
            'message_id' => ['required', 'string'],
            'type' => ['required', Rule::enum(EmailEventType::class)],
            'occurred_at' => ['required', 'date'],
        ]);

        if (EmailEvent::where('provider_event_id', $payload['event_id'])->exists()) {
            return response()->json(['status' => 'duplicate']);
        }

        $recipient = BlastRecipient::with('blast:id,campaign_id')
            ->where('provider_message_id', $payload['message_id'])
            ->first();

        if ($recipient === null) {
            return response()->json(['status' => 'ignored']);
        }

        EmailEvent::create([
            'campaign_id' => $recipient->blast->campaign_id,
            'blast_id' => $recipient->blast_id,
            'contact_id' => $recipient->contact_id,
            'type' => $payload['type'],
            'occurred_at' => $payload['occurred_at'],
            'provider_event_id' => $payload['event_id'],
        ]);

        return response()->json(['status' => 'recorded'], 201);
    }
}
