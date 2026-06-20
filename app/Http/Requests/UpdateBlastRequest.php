<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBlastRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The route-scoped {blast} is already proven to belong to {campaign}; here
     * we require the ManageContent capability via the BlastPolicy.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('blast'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The target segment is optional but, when given, must belong to the same
     * campaign — a segment from another campaign is rejected, keeping the blast
     * tenant-consistent.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'segment_id' => [
                'nullable',
                Rule::exists('segments', 'id')->where('campaign_id', $this->route('campaign')->id),
            ],
        ];
    }
}
