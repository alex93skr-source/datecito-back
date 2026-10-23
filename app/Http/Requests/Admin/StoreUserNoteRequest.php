<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('moderate') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'pinned' => ['sometimes', 'boolean'],
        ];
    }
}
