<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\Campaign;
use App\Models\User;

/**
 * Authorizes campaign-level actions.
 *
 * Every gate here delegates to the role → capability core ({@see Role::can()})
 * via {@see User::roleIn()}, so the policy never reasons about raw role strings — a
 * non-member ({@see User::roleIn()} returns null) is denied by the null-coalesce.
 */
class CampaignPolicy
{
    /**
     * Determine whether the user may update the campaign's settings.
     */
    public function update(User $user, Campaign $campaign): bool
    {
        return $user->roleIn($campaign)?->can(Capability::ManageCampaign) ?? false;
    }

    /**
     * Determine whether the user may manage the campaign's membership.
     */
    public function manageMembers(User $user, Campaign $campaign): bool
    {
        return $user->roleIn($campaign)?->can(Capability::ManageMembers) ?? false;
    }
}
