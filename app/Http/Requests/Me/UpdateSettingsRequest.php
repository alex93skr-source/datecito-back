<?php

namespace App\Http\Requests\Me;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'show_in_discover' => ['sometimes', 'boolean'],
            'show_gender' => ['sometimes', 'boolean'],
            'show_job' => ['sometimes', 'boolean'],
            'show_relationship' => ['sometimes', 'boolean'],
            'only_verified_messages' => ['sometimes', 'boolean'],
            'i_write_first' => ['sometimes', 'boolean'],
            'offensive_warning' => ['sometimes', 'boolean'],
        ];
    }
}
