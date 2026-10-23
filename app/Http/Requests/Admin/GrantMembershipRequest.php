<?php

namespace App\Http\Requests\Admin;

use App\Enums\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrantMembershipRequest extends FormRequest
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
            'plan' => ['required', Rule::enum(SubscriptionPlan::class)],
            'days' => ['required', 'integer', 'between:1,730'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
