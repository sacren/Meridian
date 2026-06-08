<?php

namespace App\Enums;

/**
 * A user's role within a single campaign (the value stored on the campaign_user pivot).
 *
 * The role → capability matrix in {@see self::can()} is the single source of truth
 * for authorization; downstream policies and middleware key off capabilities, not role strings.
 */
enum Role: string
{
    case Owner = 'owner';
    case Staffer = 'staffer';
    case Viewer = 'viewer';

    /**
     * Determine whether this role grants the given capability.
     */
    public function can(Capability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    /**
     * The capabilities granted to this role.
     *
     * @return list<Capability>
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::Owner => [
                Capability::ManageCampaign,
                Capability::ManageMembers,
                Capability::ManageContent,
                Capability::ViewContent,
            ],
            self::Staffer => [
                Capability::ManageContent,
                Capability::ViewContent,
            ],
            self::Viewer => [
                Capability::ViewContent,
            ],
        };
    }
}
