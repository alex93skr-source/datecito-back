<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\MetricsService;
use App\Support\Period;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rango de fechas y granularidad para cualquier métrica del CRM.
 */
class PeriodRequest extends FormRequest
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
            'from' => ['nullable', 'date', 'after:2020-01-01'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'interval' => ['nullable', Rule::in(Period::INTERVALS)],
            'metric' => ['nullable', Rule::in(array_keys(MetricsService::SERIES))],
            'name' => ['nullable', 'string', 'max:80'],
            'weeks' => ['nullable', 'integer', 'between:2,16'],
            'category' => ['nullable', Rule::in(['api', 'client', 'system'])],
        ];
    }

    public function period(): Period
    {
        return Period::fromInput($this->input('from'), $this->input('to'), $this->input('interval'));
    }
}
