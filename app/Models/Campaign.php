<?php

namespace App\Models;

use App\Policies\CampaignPolicy;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The tenant root: every other record belongs to a campaign, and access is gated
 * by campaign membership + role.
 */
#[Fillable(['name', 'slug'])]
#[UsePolicy(CampaignPolicy::class)]
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    /**
     * Bind the {campaign} route segment by its slug rather than its id.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The users who are members of this campaign, with their per-campaign role.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The contacts that belong to this campaign.
     *
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * The segments that belong to this campaign.
     *
     * @return HasMany<Segment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(Segment::class);
    }

    /**
     * The blasts that belong to this campaign.
     *
     * @return HasMany<Blast, $this>
     */
    public function blasts(): HasMany
    {
        return $this->hasMany(Blast::class);
    }
}
