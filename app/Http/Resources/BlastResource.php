<?php

namespace App\Http\Resources;

use App\Models\Blast;
use App\Models\Segment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The JSON representation of a {@see Blast} over the v1 API.
 *
 * @mixin Blast
 */
class BlastResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The target segment id is always exposed; a minimal {id, name} summary of
     * the segment is included only when the relation has been eager-loaded
     * (null when the draft has no target).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status->value,
            'segment_id' => $this->segment_id,
            'segment' => $this->whenLoaded('segment', fn (Segment $segment): array => $segment->only(['id', 'name'])),
            'created_at' => $this->created_at,
        ];
    }
}
