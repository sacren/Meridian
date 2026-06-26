<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Membership is already enforced by the campaign.access middleware; here we
     * additionally require the ManageContent capability via the ContactPolicy,
     * gating on the route campaign (which carries no Contact instance yet).
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Contact::class, $this->route('campaign')]);
    }

    /**
     * Normalize the email to its trim+lowercase form before validation so the
     * campaign-scoped unique check, the manual write, and the importer all agree
     * on one canonical value.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
        }
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
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('contacts', 'email')->where('campaign_id', $this->route('campaign')->id),
            ],
            'phone' => ['nullable', 'string', 'max:255'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }
}
