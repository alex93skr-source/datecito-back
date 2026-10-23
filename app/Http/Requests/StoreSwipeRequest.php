<?php

namespace App\Http\Requests;

use App\Enums\SwipeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSwipeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', 'ulid'],
            'type' => ['required', Rule::enum(SwipeType::class)],
        ];
    }
}
