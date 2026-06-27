<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Importing bulk-creates contacts, so it gates on the same ManageContent
     * ability as creating one — via the ContactPolicy against the route campaign
     * (which carries no Contact instance).
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Contact::class, $this->route('campaign')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The upload is validated before it ever touches the disk: a CSV/text file
     * within a bounded size. A text/plain CSV is guessed as `txt`, so both
     * extensions are allowed.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }
}
