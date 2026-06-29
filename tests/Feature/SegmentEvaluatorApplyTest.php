<?php

use App\Enums\SegmentOperator;
use App\Models\Campaign;
use App\Models\Contact;
use App\Segments\SegmentEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The COLLATE parity proven here is meaningless on SQLite, so this test rides on
// the MySQL `testing` database like the rest of the suite. It is the correctness
// guarantee for the query path: apply() must return the SAME contacts as the
// evaluate() oracle over the realistic domain (ASCII plus common Latin accents).
uses(RefreshDatabase::class);

/**
 * The contact fields a criteria rule may name (the evaluator's whitelist).
 *
 * @var list<string>
 */
const APPLY_FIELDS = ['name', 'email', 'phone'];

/**
 * A campaign seeded with a deliberately diverse roster: accents, case variants,
 * whitespace-only and empty and null fields, and the LIKE metacharacters % _ \.
 *
 * Emails stay unique (the (campaign_id, email) index is accent/case-insensitive)
 * while the accent/metacharacter richness lives on name and phone, which have no
 * uniqueness constraint. The same roster backs both the pinned cases and the
 * randomized property test.
 */
function applyRoster(): Campaign
{
    $campaign = Campaign::factory()->create();

    $rows = [
        ['name' => 'Ada Lovelace', 'email' => 'ada@gmail.com', 'phone' => '555-0100'],
        ['name' => 'Alan Turing', 'email' => 'alan@example.org', 'phone' => null],
        ['name' => 'Grace Hopper', 'email' => 'grace@navy.mil', 'phone' => ''],
        ['name' => 'José García', 'email' => 'jose@gmail.com', 'phone' => '   '],
        ['name' => 'Jose Garcia', 'email' => 'jose2@gmail.com', 'phone' => '555-0102'],
        ['name' => 'café owner', 'email' => 'cafe@shop.test', 'phone' => '555-0103'],
        ['name' => 'CAFE OWNER', 'email' => 'cafe2@shop.test', 'phone' => '555-0104'],
        ['name' => 'CAFÉ Deluxe', 'email' => 'cafe3@shop.test', 'phone' => null],
        ['name' => 'Discount 50%', 'email' => 'promo@gmail.com', 'phone' => '50%'],
        ['name' => 'under_score', 'email' => 'user_name@gmail.com', 'phone' => 'a_b'],
        ['name' => 'back\\slash', 'email' => 'weird@path.test', 'phone' => 'x\\y'],
        ['name' => '  padded  ', 'email' => 'pad@gmail.com', 'phone' => ' 555 '],
        ['name' => 'Zoë', 'email' => 'zoe@gmail.com', 'phone' => null],
        ['name' => 'Zoe', 'email' => 'zoe2@gmail.com', 'phone' => '555-0106'],
        ['name' => 'Renée', 'email' => 'renee@yahoo.com', 'phone' => ''],
        ['name' => 'Bob', 'email' => 'bob@yahoo.com', 'phone' => '555-0107'],
        ['name' => 'Carol', 'email' => 'carol@yahoo.com', 'phone' => '   '],
        ['name' => '', 'email' => 'empty-name@gmail.com', 'phone' => '555-0108'],
        ['name' => 'MixEdCaSe', 'email' => 'mixed@gmail.com', 'phone' => '555-0109'],
        ['name' => '100% sure', 'email' => 'sure@test.io', 'phone' => null],
    ];

    foreach ($rows as $row) {
        Contact::factory()->for($campaign)->create($row);
    }

    return $campaign;
}

/**
 * The ids the oracle matches for the given criteria, sorted.
 *
 * @param  array<string, mixed>|null  $criteria
 * @return array<int, int>
 */
function oracleIds(Campaign $campaign, ?array $criteria): array
{
    return (new SegmentEvaluator)
        ->evaluate($criteria, $campaign->contacts()->get())
        ->pluck('id')->sort()->values()->all();
}

/**
 * The ids the SQL query path matches for the given criteria, sorted.
 *
 * @param  array<string, mixed>|null  $criteria
 * @return array<int, int>
 */
function applyIds(Campaign $campaign, ?array $criteria): array
{
    return (new SegmentEvaluator)
        ->apply($campaign->contacts(), $criteria)
        ->get()->pluck('id')->sort()->values()->all();
}

/**
 * Assert the SQL path and the oracle match exactly the same contacts.
 *
 * @param  array<string, mixed>|null  $criteria
 */
function assertParity(Campaign $campaign, ?array $criteria): void
{
    expect(applyIds($campaign, $criteria))->toBe(oracleIds($campaign, $criteria));
}

/**
 * @return array<string, mixed>
 */
function rule(string $field, SegmentOperator $operator, ?string $value = null): array
{
    return array_filter(
        ['field' => $field, 'operator' => $operator->value, 'value' => $value],
        fn ($v): bool => $v !== null,
    );
}

test('empty and null criteria match every contact, like the oracle', function () {
    $campaign = applyRoster();

    assertParity($campaign, null);
    assertParity($campaign, ['combinator' => 'and', 'rules' => []]);

    expect(applyIds($campaign, null))->toHaveCount(20);
});

test('equals is case-insensitive', function () {
    $campaign = applyRoster();
    $criteria = ['rules' => [rule('name', SegmentOperator::Equals, 'ADA LOVELACE')]];

    assertParity($campaign, $criteria);
    expect(applyIds($campaign, $criteria))->toHaveCount(1);
});

test('contains is accent-sensitive: café does not match cafe', function () {
    $campaign = applyRoster();
    $criteria = ['rules' => [rule('name', SegmentOperator::Contains, 'café')]];

    assertParity($campaign, $criteria);

    // 'café owner' and 'CAFÉ Deluxe' (case-insensitive), but NOT the accentless
    // 'café owner' variants 'cafe owner' / 'CAFE OWNER'.
    $names = Contact::whereIn('id', applyIds($campaign, $criteria))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['café owner', 'CAFÉ Deluxe']);
});

test('contains treats % as a literal, not a wildcard', function () {
    $campaign = applyRoster();
    $criteria = ['rules' => [rule('name', SegmentOperator::Contains, '50%')]];

    assertParity($campaign, $criteria);
    $names = Contact::whereIn('id', applyIds($campaign, $criteria))->pluck('name')->all();
    expect($names)->toBe(['Discount 50%']);
});

test('contains treats _ as a literal, not a wildcard', function () {
    $campaign = applyRoster();
    $criteria = ['rules' => [rule('phone', SegmentOperator::Contains, 'a_b')]];

    assertParity($campaign, $criteria);
    $phones = Contact::whereIn('id', applyIds($campaign, $criteria))->pluck('phone')->all();
    expect($phones)->toBe(['a_b']);
});

test('contains treats a backslash as a literal', function () {
    $campaign = applyRoster();
    $criteria = ['rules' => [rule('name', SegmentOperator::Contains, '\\')]];

    assertParity($campaign, $criteria);
    // The only name carrying a backslash.
    $names = Contact::whereIn('id', applyIds($campaign, $criteria))->pluck('name')->all();
    expect($names)->toBe(['back\\slash']);
});

test('is_empty matches null, empty, and whitespace-only fields', function () {
    $campaign = applyRoster();
    $criteria = ['rules' => [rule('phone', SegmentOperator::IsEmpty)]];

    assertParity($campaign, $criteria);
});

test('is_not_empty is the exact complement of is_empty', function () {
    $campaign = applyRoster();

    assertParity($campaign, ['rules' => [rule('phone', SegmentOperator::IsNotEmpty)]]);
});

test('starts_with and ends_with anchor to the field edges', function () {
    $campaign = applyRoster();

    assertParity($campaign, ['rules' => [rule('email', SegmentOperator::EndsWith, '@gmail.com')]]);
    assertParity($campaign, ['rules' => [rule('name', SegmentOperator::StartsWith, 'ca')]]);
});

test('the and combinator requires every rule; or requires any', function () {
    $campaign = applyRoster();

    assertParity($campaign, ['combinator' => 'and', 'rules' => [
        rule('email', SegmentOperator::EndsWith, '@gmail.com'),
        rule('phone', SegmentOperator::IsNotEmpty),
    ]]);

    assertParity($campaign, ['combinator' => 'or', 'rules' => [
        rule('email', SegmentOperator::EndsWith, '@yahoo.com'),
        rule('name', SegmentOperator::Equals, 'Bob'),
    ]]);
});

test('an off-whitelist field fails closed: kills an AND, inert in an OR', function () {
    $campaign = applyRoster();

    // AND with a bad field → nothing matches.
    $and = ['combinator' => 'and', 'rules' => [
        rule('id', SegmentOperator::Equals, '1'),
        rule('email', SegmentOperator::EndsWith, '@gmail.com'),
    ]];
    assertParity($campaign, $and);
    expect(applyIds($campaign, $and))->toHaveCount(0);

    // OR with a bad field → inert; only the good rule contributes.
    $or = ['combinator' => 'or', 'rules' => [
        rule('id', SegmentOperator::Equals, '1'),
        rule('email', SegmentOperator::EndsWith, '@yahoo.com'),
    ]];
    assertParity($campaign, $or);
    expect(applyIds($campaign, $or))->toBe(oracleIds($campaign, ['rules' => [
        rule('email', SegmentOperator::EndsWith, '@yahoo.com'),
    ]]));
});

test('an unknown operator fails closed like the oracle', function () {
    $campaign = applyRoster();

    // tryFrom('regex') === null → fail-closed. Build the raw rule by hand.
    $criteria = ['combinator' => 'and', 'rules' => [
        ['field' => 'name', 'operator' => 'regex', 'value' => '.*'],
    ]];

    assertParity($campaign, $criteria);
    expect(applyIds($campaign, $criteria))->toHaveCount(0);
});

test('apply matches the oracle for random criteria over the realistic domain', function () {
    $campaign = applyRoster();

    // Seeded so any failure reproduces deterministically.
    mt_srand(20260707);

    $operators = SegmentOperator::cases();
    $values = [
        'a', 'A', 'café', 'cafe', 'CAFÉ', 'Zoë', 'zoe', 'gmail', '@gmail.com', '@yahoo.com',
        '.com', '50%', 'a_b', '\\', 'x\\y', '', ' ', '   ', 'José', 'jose', 'ada', 'BOB',
        '555', 'owner', 'MixEdCaSe', '%', '_',
    ];

    for ($i = 0; $i < 300; $i++) {
        $ruleCount = mt_rand(1, 3);
        $rules = [];

        for ($r = 0; $r < $ruleCount; $r++) {
            $field = APPLY_FIELDS[array_rand(APPLY_FIELDS)];
            $operator = $operators[array_rand($operators)];
            $value = $values[array_rand($values)];
            $rules[] = rule($field, $operator, $value);
        }

        $criteria = [
            'combinator' => mt_rand(0, 1) === 0 ? 'and' : 'or',
            'rules' => $rules,
        ];

        expect(applyIds($campaign, $criteria))
            ->toBe(oracleIds($campaign, $criteria), 'criteria: '.json_encode($criteria));
    }
});
