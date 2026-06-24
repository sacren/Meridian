<?php

namespace App\Enums;

/**
 * The delivery state of a single blast recipient.
 *
 * A recipient row is written {@see self::Pending} when a blast begins sending,
 * then flips to {@see self::Sent} once the email provider accepts it (capturing
 * the returned message id) or {@see self::Failed} if the provider rejects it. The
 * blast's own {@see BlastStatus} settles once every recipient leaves Pending.
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
