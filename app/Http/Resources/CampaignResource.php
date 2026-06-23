<?php

namespace App\Http\Resources;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The JSON representation of a {@see Campaign} over the v1 API.
 *
 * @mixin Campaign
 */
class CampaignResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The caller's role is exposed only when the campaign_user pivot is loaded
     * (the index action lists the caller's own memberships); route-model-bound
     * lookups such as show carry no pivot, so the role is omitted there.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'created_at' => $this->created_at,
            'role' => $this->whenPivotLoaded('campaign_user', fn () => $this->pivot->role),
        ];
    }
}
