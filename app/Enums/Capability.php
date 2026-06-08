<?php

namespace App\Enums;

/**
 * A single authorization capability.
 *
 * Authorization callers (policies, middleware) depend on these capabilities,
 * never on raw role strings. The role → capability matrix lives on {@see Role::can()}.
 */
enum Capability
{
    case ManageCampaign;
    case ManageMembers;
    case ManageContent;
    case ViewContent;
}
