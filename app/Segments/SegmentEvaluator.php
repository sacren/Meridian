<?php

namespace App\Segments;

use App\Enums\SegmentOperator;
use App\Models\Contact;
use Illuminate\Support\Collection;

/**
 * Evaluates a segment's criteria against a set of contacts.
 *
 * The criteria shape is the pinned schema: a flat list of {field, operator, value}
 * rules joined by a single AND/OR combinator (see the schema decision export). This
 * implementation is a Collection pipeline; the Performance stage may later convert it
 * to a query without changing the schema or this class's public surface.
 *
 * It is deliberately defensive: a rule naming a field outside {@see self::FIELDS} or
 * an operator outside {@see SegmentOperator} fails closed (that rule matches nothing),
 * so malformed criteria can never widen a segment or raise an error here. Write-time
 * validation (S4) is the primary guard; this is the safety net.
 */
class SegmentEvaluator
{
    /**
     * The contact fields a criteria rule may filter on (the whitelist).
     *
     * @var list<string>
     */
    public const FIELDS = ['name', 'email', 'phone'];

    /**
     * Filter the given contacts down to those matching the criteria.
     *
     * Null or empty criteria (no rules) matches every contact.
     *
     * @param  array{combinator?: string, rules?: array<int, array<string, mixed>>}|null  $criteria
     * @param  Collection<int, Contact>  $contacts
     * @return Collection<int, Contact>
     */
    public function evaluate(?array $criteria, Collection $contacts): Collection
    {
        $rules = $criteria['rules'] ?? [];

        if ($rules === []) {
            return $contacts->values();
        }

        $matchAll = ($criteria['combinator'] ?? 'and') !== 'or';

        return $contacts
            ->filter(fn (Contact $contact): bool => $this->contactMatches($contact, $rules, $matchAll))
            ->values();
    }

    /**
     * Whether a contact satisfies the rule set under the combinator.
     *
     * @param  array<int, array<string, mixed>>  $rules
     */
    protected function contactMatches(Contact $contact, array $rules, bool $matchAll): bool
    {
        foreach ($rules as $rule) {
            $matches = $this->ruleMatches($contact, $rule);

            if ($matchAll && ! $matches) {
                return false;
            }

            if (! $matchAll && $matches) {
                return true;
            }
        }

        // AND: every rule matched. OR: none matched.
        return $matchAll;
    }

    /**
     * Whether a single rule matches the contact, failing closed on bad input.
     *
     * @param  array<string, mixed>  $rule
     */
    protected function ruleMatches(Contact $contact, array $rule): bool
    {
        $field = $rule['field'] ?? null;

        if (! in_array($field, self::FIELDS, true)) {
            return false;
        }

        $operator = SegmentOperator::tryFrom((string) ($rule['operator'] ?? ''));

        if ($operator === null) {
            return false;
        }

        $value = $rule['value'] ?? null;

        return $operator->matches(
            $contact->{$field},
            is_scalar($value) ? (string) $value : null,
        );
    }
}
