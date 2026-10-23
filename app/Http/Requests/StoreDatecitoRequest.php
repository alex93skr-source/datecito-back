<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDatecitoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(config('datecito.catalog.plan_kinds'))],
            'place' => ['required', 'string', 'min:2', 'max:80'],
            'zone' => ['required', 'string', 'min:2', 'max:60'],
            // Día y hora del calendario (con zona horaria, ej. 2026-10-18T18:00:00-06:00).
            'scheduled_at' => ['required', 'date', 'after:now', 'before:'.now()->addMonths(3)->toIso8601String()],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }
}
