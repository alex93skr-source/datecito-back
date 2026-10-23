<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'string', 'ulid', Rule::exists('users', 'id')],
            'reason' => ['required', Rule::in(config('datecito.catalog.report_reasons'))],
            'details' => ['nullable', 'string', 'max:2000'],
            'also_block' => ['sometimes', 'boolean'],
        ];
    }
}
