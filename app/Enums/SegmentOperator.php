<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * A comparison operator usable in a segment's criteria rule.
 *
 * This enum is the single source of truth for the operator set: the evaluator,
 * the write validation, and the UI builder all key off these cases. The backing
 * value is the token stored inside the criteria JSON.
 */
enum SegmentOperator: string
{
    case Equals = 'equals';
    case Contains = 'contains';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';
    case IsEmpty = 'is_empty';
    case IsNotEmpty = 'is_not_empty';

    /**
     * Whether this operator compares against a user-supplied value.
     *
     * The presence operators ({@see self::IsEmpty}, {@see self::IsNotEmpty}) ignore
     * the rule's value entirely.
     */
    public function requiresValue(): bool
    {
        return ! in_array($this, [self::IsEmpty, self::IsNotEmpty], true);
    }

    /**
     * Determine whether a contact field value satisfies this operator.
     *
     * Text comparisons are case-insensitive; the presence operators use Laravel's
     * blank()/filled() semantics, so both null and "" count as empty.
     */
    public function matches(?string $actual, ?string $value): bool
    {
        return match ($this) {
            self::IsEmpty => blank($actual),
            self::IsNotEmpty => filled($actual),
            self::Equals => Str::lower($actual ?? '') === Str::lower($value ?? ''),
            self::Contains => Str::contains(Str::lower($actual ?? ''), Str::lower($value ?? '')),
            self::StartsWith => Str::startsWith(Str::lower($actual ?? ''), Str::lower($value ?? '')),
            self::EndsWith => Str::endsWith(Str::lower($actual ?? ''), Str::lower($value ?? '')),
        };
    }
}
