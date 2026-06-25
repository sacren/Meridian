<?php

namespace App\Models;

use App\Enums\EmailEventType;
use Database\Factories\EmailEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A normalized inbound provider event attributed to one delivery: the recipient
 * opened or clicked the blast, or it bounced. The webhook correlates a provider
 * payload back to a (blast, contact) pair through the delivery's message id and
 * records it here, deduped on provider_event_id. These events are the raw
 * material the campaign analytics rollups aggregate.
 */
#[Fillable(['campaign_id', 'blast_id', 'contact_id', 'type', 'occurred_at', 'provider_event_id'])]
class EmailEvent extends Model
{
    /** @use HasFactory<EmailEventFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EmailEventType::class,
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * The campaign this event belongs to.
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * The blast this event was reported against.
     *
     * @return BelongsTo<Blast, $this>
     */
    public function blast(): BelongsTo
    {
        return $this->belongsTo(Blast::class);
    }

    /**
     * The contact this event is attributed to.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
