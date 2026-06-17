<?php

use App\Models\Contact;
use App\Segments\SegmentEvaluator;
use Illuminate\Support\Collection;
use Tests\TestCase;

// Boot the Laravel app (for Eloquent models + helpers) but no database is touched:
// the evaluator works purely on in-memory contacts, so this stays a fast unit test.
uses(TestCase::class);

/**
 * Build an in-memory contact (never persisted) with the given attributes.
 */
function contact(string $name, string $email, ?string $phone = null): Contact
{
    return new Contact(['name' => $name, 'email' => $email, 'phone' => $phone]);
}

/**
 * The fixed roster used across the matching cases.
 *
 * @return Collection<int, Contact>
 */
function roster(): Collection
{
    return collect([
        contact('Ada Lovelace', 'ada@example.com', '555-0100'),
        contact('Alan Turing', 'alan@example.org', null),
        contact('Grace Hopper', 'grace@navy.mil', ''),
    ]);
}

/**
 * @param  array<string, mixed>|null  $criteria
 * @return Collection<int, Contact>
 */
function evaluateCriteria(?array $criteria): Collection
{
    return (new SegmentEvaluator)->evaluate($criteria, roster());
}

test('null criteria matches every contact', function () {
    expect(evaluateCriteria(null))->toHaveCount(3);
});

test('empty rules match every contact', function () {
    expect(evaluateCriteria(['combinator' => 'and', 'rules' => []]))->toHaveCount(3);
});

test('equals matches case-insensitively', function () {
    $matches = evaluateCriteria(['rules' => [
        ['field' => 'name', 'operator' => 'equals', 'value' => 'ADA LOVELACE'],
    ]]);

    expect($matches)->toHaveCount(1)
        ->and($matches->first()->email)->toBe('ada@example.com');
});

test('contains matches a substring case-insensitively', function () {
    $matches = evaluateCriteria(['rules' => [
        ['field' => 'email', 'operator' => 'contains', 'value' => 'EXAMPLE'],
    ]]);

    expect($matches->pluck('name')->all())->toEqualCanonicalizing(['Ada Lovelace', 'Alan Turing']);
});

test('starts_with and ends_with match the field edges', function () {
    expect(evaluateCriteria(['rules' => [
        ['field' => 'name', 'operator' => 'starts_with', 'value' => 'Al'],
    ]])->pluck('name')->all())->toBe(['Alan Turing']);

    expect(evaluateCriteria(['rules' => [
        ['field' => 'email', 'operator' => 'ends_with', 'value' => '.mil'],
    ]])->pluck('name')->all())->toBe(['Grace Hopper']);
});

test('is_empty treats both null and empty string as empty', function () {
    $matches = evaluateCriteria(['rules' => [
        ['field' => 'phone', 'operator' => 'is_empty'],
    ]]);

    expect($matches->pluck('name')->all())->toEqualCanonicalizing(['Alan Turing', 'Grace Hopper']);
});

test('is_not_empty matches only a present value', function () {
    $matches = evaluateCriteria(['rules' => [
        ['field' => 'phone', 'operator' => 'is_not_empty'],
    ]]);

    expect($matches->pluck('name')->all())->toBe(['Ada Lovelace']);
});

test('the and combinator requires every rule to match', function () {
    $matches = evaluateCriteria(['combinator' => 'and', 'rules' => [
        ['field' => 'name', 'operator' => 'starts_with', 'value' => 'A'],
        ['field' => 'email', 'operator' => 'ends_with', 'value' => '.com'],
    ]]);

    // Ada starts with A AND ends with .com; Alan starts with A but ends .org.
    expect($matches->pluck('name')->all())->toBe(['Ada Lovelace']);
});

test('the or combinator matches when any rule matches', function () {
    $matches = evaluateCriteria(['combinator' => 'or', 'rules' => [
        ['field' => 'email', 'operator' => 'ends_with', 'value' => '.mil'],
        ['field' => 'name', 'operator' => 'equals', 'value' => 'Ada Lovelace'],
    ]]);

    expect($matches->pluck('name')->all())->toEqualCanonicalizing(['Ada Lovelace', 'Grace Hopper']);
});

test('a rule naming an off-whitelist field fails closed', function () {
    // "id" is not filterable; the rule matches nothing, so an AND set yields nothing.
    $matches = evaluateCriteria(['combinator' => 'and', 'rules' => [
        ['field' => 'id', 'operator' => 'equals', 'value' => '1'],
    ]]);

    expect($matches)->toHaveCount(0);
});

test('a rule with an unknown operator fails closed', function () {
    $matches = evaluateCriteria(['combinator' => 'and', 'rules' => [
        ['field' => 'name', 'operator' => 'regex', 'value' => '.*'],
    ]]);

    expect($matches)->toHaveCount(0);
});
