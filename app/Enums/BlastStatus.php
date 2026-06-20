<?php

namespace App\Enums;

/**
 * The lifecycle state of a blast.
 *
 * Only {@see self::Draft} exists while blasts remain composable-but-unsent. The
 * sending states (Sending/Sent/Failed) are introduced alongside the email
 * provider integration, since they only become meaningful once a blast leaves
 * the application; until then a blast is always a draft.
 */
enum BlastStatus: string
{
    case Draft = 'draft';
}
