<?php

namespace App\Enums;

/**
 * The lifecycle state of a blast.
 *
 * A blast begins as a {@see self::Draft} while it remains composable. Sending it
 * fans out one queued delivery per recipient through the email provider seam,
 * moving it to {@see self::Sending}; once every delivery is accounted for it
 * settles on {@see self::Sent}, or {@see self::Failed} if the fan-out could not
 * complete. A blast is send-once and becomes read-only the moment it leaves
 * Draft.
 */
enum BlastStatus: string
{
    case Draft = 'draft';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
}
