<?php

namespace App\Http\Requests;

use App\Concerns\SegmentCriteriaRules;
use App\Models\Segment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSegmentRequest extends FormRequest
{
    use SegmentCriteriaRules;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Membership is enforced by the campaign.access middleware; here we additionally
     * require the ManageContent capability via the SegmentPolicy, gating on the route
     * campaign (which carries no Segment instance yet).
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Segment::class, $this->route('campaign')]);
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
