<?php

namespace App\Models;

use Database\Factories\SegmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved filter over a campaign's contacts. The criteria JSON column holds the
 * filter definition; its internal schema is pinned and evaluated in the Segment
 * evaluator (S2), so here it is simply cast to an array.
 */
#[Fillable(['name', 'criteria'])]
class Segment extends Model
{
    /** @use HasFactory<SegmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'criteria' => 'array',
        ];
    }

    /**
     * The campaign this segment belongs to.
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
