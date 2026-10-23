<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHereSessionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'place' => ['required', 'string', 'min:2', 'max:80'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'minutes' => ['required', 'integer', Rule::in(config('datecito.limits.here_minutes'))],
            'verified_only' => ['required', 'boolean'],
        ];
    }
}
