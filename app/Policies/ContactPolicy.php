<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\User;

/**
 * Authorizes contact actions within a campaign.
 *
 * Like {@see CampaignPolicy}, every gate delegates to the role → capability core
 * ({@see Role::can()}) via {@see User::roleIn()}, so a non-member
 * ({@see User::roleIn()} returns null) is denied by the null-coalesce. The
 * model-less gates ({@see self::viewAny()}, {@see self::create()}) receive the
 * route campaign explicitly; the model gates resolve it from the contact.
 */
class ContactPolicy
{
    /**
     * Determine whether the user may list the campaign's contacts.
     */
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->roleIn($campaign)?->can(Capability::ViewContent) ?? false;
    }

    /**
     * Determine whether the user may view the contact.
     */
    public function view(User $user, Contact $contact): bool
    {
        return $user->roleIn($contact->campaign)?->can(Capability::ViewContent) ?? false;
    }

    /**
     * Determine whether the user may add a contact to the campaign.
     */
    public function create(User $user, Campaign $campaign): bool
    {
        return $user->roleIn($campaign)?->can(Capability::ManageContent) ?? false;
    }

    /**
     * Determine whether the user may update the contact.
     */
    public function update(User $user, Contact $contact): bool
    {
        return $user->roleIn($contact->campaign)?->can(Capability::ManageContent) ?? false;
    }

    /**
     * Determine whether the user may delete the contact.
     */
    public function delete(User $user, Contact $contact): bool
    {
        return $user->roleIn($contact->campaign)?->can(Capability::ManageContent) ?? false;
    }
}
