<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Database\Factories\BlastRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One blast's delivery to one contact: the per-recipient record the send pipeline
 * writes as it fans out. Its provider_message_id is the correlation key an inbound
 * webhook event uses to attribute itself back to this (blast, contact) pair, and
 * its status tracks that single delivery independently of the blast as a whole.
 */
#[Fillable(['blast_id', 'contact_id', 'provider_message_id', 'status', 'sent_at', 'failed_at'])]
class BlastRecipient extends Model
{
    /** @use HasFactory<BlastRecipientFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * The blast this delivery belongs to.
     *
     * @return BelongsTo<Blast, $this>
     */
    public function blast(): BelongsTo
    {
        return $this->belongsTo(Blast::class);
    }

    /**
     * The contact this delivery was addressed to.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
