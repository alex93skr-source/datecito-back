<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSafeSessionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'datecito_id' => ['required', 'string', 'ulid'],
            'contact_ids' => ['required', 'array', 'min:1', 'max:'.config('datecito.limits.trusted_contacts_max')],
            'contact_ids.*' => ['required', 'string', 'ulid', 'distinct'],
            'check_in_minutes' => ['required', 'integer', Rule::in(config('datecito.limits.safe_check_in_minutes'))],
            'share_location' => ['required', 'boolean'],
        ];
    }
}
