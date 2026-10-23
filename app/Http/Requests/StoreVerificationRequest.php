<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVerificationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'selfie' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288', 'dimensions:min_width=320,min_height=320,max_width=5000,max_height=5000'],
            'pose' => ['required', 'string', Rule::in(config('datecito.catalog.verification_poses'))],
        ];
    }
}
