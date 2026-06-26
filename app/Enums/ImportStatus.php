<?php

namespace App\Enums;

/**
 * The lifecycle state of a CSV contact import.
 *
 * An import record is created {@see self::Pending} the moment the upload is
 * stored; the queued job flips it to {@see self::Processing} as it begins
 * streaming the file, then settles on {@see self::Completed} once every row is
 * accounted for, or {@see self::Failed} if the file could not be processed at
 * all (an unreadable stream) — a per-row problem is captured in the error
 * report, not by failing the whole import.
 */
enum ImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
