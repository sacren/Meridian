<?php

namespace App\Http\Requests;

use App\Models\Blast;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBlastRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Membership is already enforced by the campaign.access middleware; here we
     * additionally require the ManageContent capability via the BlastPolicy,
     * gating on the route campaign (which carries no Blast instance yet).
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Blast::class, $this->route('campaign')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The target segment is optional (a draft may be composed before a target is
     * chosen) but, when given, must belong to the same campaign — a segment from
     * another campaign is rejected, keeping the blast tenant-consistent.
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
