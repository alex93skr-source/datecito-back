<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SyncUserTagsRequest extends FormRequest
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
            'tags' => ['present', 'array', 'max:20'],
            'tags.*' => ['string', 'regex:/^[\p{L}\p{N} _\-]{1,40}$/u'],
        ];
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return collect($this->validated('tags'))
            ->map(fn (string $tag) => mb_strtolower(trim($tag)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
