<?php

namespace App\Http\Requests\Me;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Filtros de Descubrir. No existen filtros por apariencia física.
 */
class UpdateFiltersRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $catalog = config('datecito.catalog');
        $modes = ['intents', 'relationships', 'interests', 'genres', 'lifestyle', 'languages'];

        return [
            'meet' => ['required', Rule::in($catalog['interested_in'])],
            'minAge' => ['required', 'integer', 'min:18', 'max:99'],
            'maxAge' => ['required', 'integer', 'gte:minAge', 'max:99'],
            'km' => ['required', 'integer', 'min:1', 'max:200'],
            'intents' => ['present', 'array'],
            'intents.*' => [Rule::in($catalog['intents'])],
            'relationships' => ['present', 'array'],
            'relationships.*' => [Rule::in($catalog['relationship_types'])],
            'interests' => ['present', 'array', 'max:30'],
            'interests.*' => [Rule::in(Arr::flatten($catalog['interests']))],
            'genres' => ['present', 'array'],
            'genres.*' => [Rule::in($catalog['genres'])],
            'lifestyle' => ['present', 'array'],
            'lifestyle.*' => [Rule::in(Arr::flatten($catalog['basics']))],
            'languages' => ['present', 'array'],
            'languages.*' => [Rule::in($catalog['languages'])],
            'verifiedOnly' => ['required', 'boolean'],
            'modes' => ['present', 'array:'.implode(',', $modes)],
            'modes.*' => [Rule::in(['prefer', 'only'])],
        ];
    }
}
