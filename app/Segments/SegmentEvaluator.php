<?php

namespace App\Segments;

use App\Enums\SegmentOperator;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
     * The collation forced on every text comparison in {@see self::apply()}.
     *
     * PHP's {@see Str::lower} makes {@see SegmentOperator::matches()}
     * case-INsensitive but accent-SENSITIVE ('café' !== 'cafe'). The contact columns
     * collate accent-INsensitively (utf8mb4_unicode_ci), so a bare comparison would
     * wrongly match 'café' to 'cafe' and diverge from the oracle. This collation is
     * accent-Sensitive, case-Insensitive, and NO PAD (trailing spaces significant),
     * which is exactly the oracle's semantics — pinned per comparison, no migration.
     */
    protected const COLLATION = 'utf8mb4_0900_as_ci';

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

    /**
     * Constrain a contacts query to those matching the criteria, in SQL.
     *
     * This is the query-path twin of {@see self::evaluate()}: instead of
     * materializing every contact and filtering in PHP, it pushes the same
     * matching logic into the database so the preview and blast-send audience
     * scale to large books. It is proven behavior-equivalent to the oracle over
     * the realistic domain (ASCII plus common Latin accents) by the differential
     * test; rare exotic-Unicode residuals where a MySQL collation diverges from
     * PHP's mb_strtolower (ß→ss, dotless ı, ligatures) are a known, accepted
     * divergence, not a byte-perfect claim.
     *
     * All rule predicates are wrapped in one nested where group so the caller's
     * pre-existing constraints (campaign scope, ordering) still compose with AND.
     * Null or empty criteria (no rules) leaves the query untouched (matches all).
     *
     * @param  Relation<Contact, *, *>|Builder<Contact>  $query
     * @param  array{combinator?: string, rules?: array<int, array<string, mixed>>}|null  $criteria
     * @return Builder<Contact>
     */
    public function apply(Relation|Builder $query, ?array $criteria): Builder
    {
        $builder = $query instanceof Relation ? $query->getQuery() : $query;

        $rules = $criteria['rules'] ?? [];

        if ($rules === []) {
            return $builder;
        }

        $matchAll = ($criteria['combinator'] ?? 'and') !== 'or';

        return $builder->where(function (Builder $inner) use ($rules, $matchAll): void {
            foreach ($rules as $rule) {
                $this->applyRule($inner, $rule, $matchAll);
            }
        });
    }

    /**
     * Add a single rule's predicate to the nested where group, failing closed.
     *
     * Mirrors {@see self::ruleMatches()} exactly: a field outside {@see self::FIELDS}
     * or an unknown operator becomes an always-false predicate — which kills an AND
     * group and is inert in an OR group, matching the oracle's fail-closed rule.
     *
     * @param  Builder<Contact>  $inner
     * @param  array<string, mixed>  $rule
     */
    protected function applyRule(Builder $inner, array $rule, bool $matchAll): void
    {
        $field = $rule['field'] ?? null;
        $operator = SegmentOperator::tryFrom((string) ($rule['operator'] ?? ''));

        if (! in_array($field, self::FIELDS, true) || $operator === null) {
            $this->addPredicate($inner, $matchAll, '1 = 0');

            return;
        }

        $value = $rule['value'] ?? null;
        $value = is_scalar($value) ? (string) $value : '';

        // Laravel's Str::contains/startsWith/endsWith treat an empty needle as no
        // match (they skip ''), so an empty value on those operators must fail
        // closed rather than become LIKE '%%' (which would match everything).
        if ($value === '' && in_array($operator, [SegmentOperator::Contains, SegmentOperator::StartsWith, SegmentOperator::EndsWith], true)) {
            $this->addPredicate($inner, $matchAll, '1 = 0');

            return;
        }

        // $field is whitelist-confirmed above, so interpolating it is safe; the
        // COALESCE mirrors the oracle coercing a null actual to '', and the pinned
        // collation gives case-insensitive, accent-sensitive comparison.
        $column = "COALESCE(`{$field}`, '') COLLATE ".self::COLLATION;
        $bare = "`{$field}`";

        match ($operator) {
            SegmentOperator::Equals => $this->addPredicate($inner, $matchAll, "{$column} = ?", [$value]),
            SegmentOperator::Contains => $this->addPredicate($inner, $matchAll, "{$column} LIKE ? ESCAPE '\\\\'", ['%'.$this->escapeLike($value).'%']),
            SegmentOperator::StartsWith => $this->addPredicate($inner, $matchAll, "{$column} LIKE ? ESCAPE '\\\\'", [$this->escapeLike($value).'%']),
            SegmentOperator::EndsWith => $this->addPredicate($inner, $matchAll, "{$column} LIKE ? ESCAPE '\\\\'", ['%'.$this->escapeLike($value)]),
            SegmentOperator::IsEmpty => $this->addPredicate($inner, $matchAll, "({$bare} IS NULL OR TRIM({$bare}) = '')"),
            SegmentOperator::IsNotEmpty => $this->addPredicate($inner, $matchAll, "({$bare} IS NOT NULL AND TRIM({$bare}) <> '')"),
        };
    }

    /**
     * Add a raw predicate to the group under the combinator's boolean.
     *
     * @param  Builder<Contact>  $inner
     * @param  list<mixed>  $bindings
     */
    protected function addPredicate(Builder $inner, bool $matchAll, string $sql, array $bindings = []): void
    {
        if ($matchAll) {
            $inner->whereRaw($sql, $bindings);
        } else {
            $inner->orWhereRaw($sql, $bindings);
        }
    }

    /**
     * Escape LIKE metacharacters so the value matches literally.
     *
     * The oracle's Str::contains/startsWith/endsWith are literal substring tests,
     * so a value of "50%" or "a_b" must match those characters literally, not as
     * wildcards. Backslash is escaped first (so it does not double-escape the ones
     * added after), then % and _; the callers pair this with ESCAPE '\'.
     */
    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
