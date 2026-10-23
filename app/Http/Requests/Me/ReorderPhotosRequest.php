<?php

namespace App\Http\Requests\Me;

use Illuminate\Foundation\Http\FormRequest;

class ReorderPhotosRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'max:'.config('datecito.limits.photos_max')],
            'ids.*' => ['required', 'string', 'ulid', 'distinct'],
        ];
    }
}
