<?php

namespace App\Enums;

/**
 * The kind of inbound provider event recorded against a delivery.
 *
 * A provider reports what happened to a sent message: the recipient opened it
 * ({@see self::Open}), clicked a link in it ({@see self::Click}), or it bounced
 * ({@see self::Bounce}). These are the normalized types the webhook maps every
 * provider's payload onto, independent of any one provider's vocabulary.
 */
enum EmailEventType: string
{
    case Open = 'open';
    case Click = 'click';
    case Bounce = 'bounce';
}
