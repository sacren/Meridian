<?php

namespace App\Policies;

use App\Enums\BlastStatus;
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
     *
     * A blast is send-once: once it leaves Draft it becomes read-only, so editing
     * requires both the ManageContent capability and a still-Draft status.
     */
    public function update(User $user, Blast $blast): bool
    {
        return ($user->roleIn($blast->campaign)?->can(Capability::ManageContent) ?? false)
            && $blast->status === BlastStatus::Draft;
    }

    /**
     * Determine whether the user may delete the blast.
     *
     * Like {@see self::update()}, a blast that has left Draft is locked and can no
     * longer be deleted.
     */
    public function delete(User $user, Blast $blast): bool
    {
        return ($user->roleIn($blast->campaign)?->can(Capability::ManageContent) ?? false)
            && $blast->status === BlastStatus::Draft;
    }

    /**
     * Determine whether the user may send the blast.
     *
     * Sending both requires the ManageContent capability and enforces send-once:
     * only a Draft may be sent, so a sending/sent/failed blast can never be re-sent.
     */
    public function send(User $user, Blast $blast): bool
    {
        return ($user->roleIn($blast->campaign)?->can(Capability::ManageContent) ?? false)
            && $blast->status === BlastStatus::Draft;
    }
}
