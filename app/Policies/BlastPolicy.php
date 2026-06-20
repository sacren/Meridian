<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\Blast;
use App\Models\Campaign;
use App\Models\User;

/**
 * Authorizes blast actions within a campaign.
 *
 * Like {@see SegmentPolicy}, every gate delegates to the role → capability core
 * ({@see Role::can()}) via {@see User::roleIn()}, so a non-member
 * ({@see User::roleIn()} returns null) is denied by the null-coalesce. The
 * model-less gates ({@see self::viewAny()}, {@see self::create()}) receive the
 * route campaign explicitly; the model gates resolve it from the blast.
 */
class BlastPolicy
{
    /**
     * Determine whether the user may list the campaign's blasts.
     */
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->roleIn($campaign)?->can(Capability::ViewContent) ?? false;
    }

    /**
     * Determine whether the user may view the blast.
     */
    public function view(User $user, Blast $blast): bool
    {
        return $user->roleIn($blast->campaign)?->can(Capability::ViewContent) ?? false;
    }

    /**
     * Determine whether the user may add a blast to the campaign.
     */
    public function create(User $user, Campaign $campaign): bool
    {
        return $user->roleIn($campaign)?->can(Capability::ManageContent) ?? false;
    }

    /**
     * Determine whether the user may update the blast.
     */
    public function update(User $user, Blast $blast): bool
    {
        return $user->roleIn($blast->campaign)?->can(Capability::ManageContent) ?? false;
    }

    /**
     * Determine whether the user may delete the blast.
     */
    public function delete(User $user, Blast $blast): bool
    {
        return $user->roleIn($blast->campaign)?->can(Capability::ManageContent) ?? false;
    }
}
