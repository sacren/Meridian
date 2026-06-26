<?php

namespace App\Models;

use App\Enums\BlastStatus;
use App\Policies\BlastPolicy;
use Database\Factories\BlastFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An email composed within a campaign and aimed at a segment of its contacts.
 * While the domain has no email provider, a blast is always a draft: its status
 * is cast to {@see BlastStatus}, which carries only the Draft case for now. The
 * target segment_id is nullable because a draft may be composed before a target
 * is chosen, and it is nulled (not cascaded) when the target segment is deleted.
 */
#[Fillable(['subject', 'body', 'status', 'segment_id'])]
#[UsePolicy(BlastPolicy::class)]
class Blast extends Model
{
    /** @use HasFactory<BlastFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BlastStatus::class,
        ];
    }

    /**
     * The campaign this blast belongs to.
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * The segment this blast targets, if one has been chosen.
     *
     * @return BelongsTo<Segment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    /**
     * The per-recipient delivery records produced when this blast is sent.
     *
     * @return HasMany<BlastRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(BlastRecipient::class);
    }

    /**
     * The inbound provider events (opens, clicks, bounces) reported against this
     * blast's deliveries — the raw material the campaign analytics rollups count.
     *
     * @return HasMany<EmailEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(EmailEvent::class);
    }
}
