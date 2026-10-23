<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdjustSuperLikesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delta' => ['required', 'integer', 'between:-1000,1000', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
