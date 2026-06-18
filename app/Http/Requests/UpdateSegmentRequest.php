<?php

namespace App\Http\Requests;

use App\Concerns\SegmentCriteriaRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSegmentRequest extends FormRequest
{
    use SegmentCriteriaRules;

    /**
     * Determine if the user is authorized to make this request.
     *
     * The route-scoped {segment} is already proven to belong to {campaign}; here
     * we require the ManageContent capability via the SegmentPolicy.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('segment'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            ...$this->criteriaRules(),
        ];
    }
}
