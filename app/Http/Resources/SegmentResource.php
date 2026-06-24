<?php

namespace App\Http\Resources;

use App\Models\Segment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The JSON representation of a {@see Segment} over the v1 API.
 *
 * @mixin Segment
 */
class SegmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'criteria' => $this->criteria,
            'created_at' => $this->created_at,
        ];
    }
}
