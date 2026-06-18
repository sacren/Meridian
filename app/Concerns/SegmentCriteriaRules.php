<?php

namespace App\Concerns;

use App\Enums\SegmentOperator;
use App\Segments\SegmentEvaluator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait SegmentCriteriaRules
{
    /**
     * Get the validation rules for a segment's criteria.
     *
     * The field and operator whitelists are sourced from the same places the
     * evaluator reads ({@see SegmentEvaluator::FIELDS}, {@see SegmentOperator}),
     * so validation and evaluation stay in lockstep: a rule the evaluator can run
     * is exactly a rule that validates, and nothing off-schema can be stored.
     * Criteria is optional (null/empty means "match all"); a rule's value is
     * required for every operator except the presence operators.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function criteriaRules(): array
    {
        $fields = SegmentEvaluator::FIELDS;
        $operators = array_map(fn (SegmentOperator $operator): string => $operator->value, SegmentOperator::cases());

        return [
            'criteria' => ['nullable', 'array'],
            'criteria.combinator' => ['nullable', Rule::in(['and', 'or'])],
            'criteria.rules' => ['nullable', 'array'],
            'criteria.rules.*' => ['array'],
            'criteria.rules.*.field' => ['required', 'string', Rule::in($fields)],
            'criteria.rules.*.operator' => ['required', 'string', Rule::in($operators)],
            'criteria.rules.*.value' => [
                'nullable',
                'required_unless:criteria.rules.*.operator,is_empty,is_not_empty',
                'string',
                'max:255',
            ],
        ];
    }
}
