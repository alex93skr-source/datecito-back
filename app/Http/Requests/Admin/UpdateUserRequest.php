<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Edición editorial por moderación (por ejemplo, retirar un texto ofensivo del perfil).
 */
class UpdateUserRequest extends FormRequest
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
            'name' => ['sometimes', 'nullable', 'string', 'max:40'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:300'],
            'job' => ['sometimes', 'nullable', 'string', 'max:60'],
            'zone' => ['sometimes', 'nullable', 'string', 'max:60'],
            'anthem' => ['sometimes', 'nullable', 'string', 'max:120'],
            'show_in_discover' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
