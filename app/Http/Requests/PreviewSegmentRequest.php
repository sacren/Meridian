<?php

namespace App\Http\Requests;

use App\Concerns\SegmentCriteriaRules;
use App\Models\Segment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewSegmentRequest extends FormRequest
{
    use SegmentCriteriaRules;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Preview only reads the campaign's contacts, so it requires the ViewContent
     * capability (the same gate as listing segments), not ManageContent.
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', [Segment::class, $this->route('campaign')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Preview validates only the criteria — it carries no name — reusing the same
     * schema rules as store/update so a preview can never run off-schema criteria.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->criteriaRules();
    }
}
