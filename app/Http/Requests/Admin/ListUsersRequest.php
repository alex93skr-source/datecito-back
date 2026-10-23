<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends FormRequest
{
    public const SORTS = ['created_at', 'last_seen_at', 'name', 'email', 'photos_count', 'swipes_count', 'reports_received_count', 'super_likes_balance'];

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
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'verification' => ['nullable', Rule::in(['none', 'pending', 'approved', 'rejected'])],
            'email_verified' => ['nullable', 'boolean'],
            'gender' => ['nullable', 'string', 'max:40'],
            'zone' => ['nullable', 'string', 'max:60'],
            'platform' => ['nullable', Rule::in(['ios', 'android', 'web'])],
            'membership' => ['nullable', Rule::in(['active', 'none'])],
            'has_photos' => ['nullable', 'boolean'],
            'tag' => ['nullable', 'string', 'max:40'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'seen_from' => ['nullable', 'date'],
            'seen_to' => ['nullable', 'date', 'after_or_equal:seen_from'],
            'min_reports' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
        ];
    }
}
