<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\Campaign;
use App\Models\Segment;
use App\Models\User;

/**
 * Authorizes segment actions within a campaign.
 *
 * Like {@see ContactPolicy}, every gate delegates to the role → capability core
 * ({@see Role::can()}) via {@see User::roleIn()}, so a non-member
 * ({@see User::roleIn()} returns null) is denied by the null-coalesce. The
 * model-less gates ({@see self::viewAny()}, {@see self::create()}) receive the
 * route campaign explicitly; the model gates resolve it from the segment.
 */
class SegmentPolicy
{
    /**
     * Determine whether the user may list the campaign's segments.
     */
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->roleIn($campaign)?->can(Capability::ViewContent) ?? false;
    }

    /**
     * Determine whether the user may view the segment.
     */
    public function view(User $user, Segment $segment): bool
    {
        return $user->roleIn($segment->campaign)?->can(Capability::ViewContent) ?? false;
    }

    /**
     * Determine whether the user may add a segment to the campaign.
     */
    public function create(User $user, Campaign $campaign): bool
    {
        return $user->roleIn($campaign)?->can(Capability::ManageContent) ?? false;
    }

    /**
     * Determine whether the user may update the segment.
     */
    public function update(User $user, Segment $segment): bool
    {
        return $user->roleIn($segment->campaign)?->can(Capability::ManageContent) ?? false;
    }

    /**
     * Determine whether the user may delete the segment.
     */
    public function delete(User $user, Segment $segment): bool
    {
        return $user->roleIn($segment->campaign)?->can(Capability::ManageContent) ?? false;
    }
}
