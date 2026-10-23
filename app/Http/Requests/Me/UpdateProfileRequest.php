<?php

namespace App\Http\Requests\Me;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Campos editables del perfil. Todo valor de catálogo se valida contra config/datecito.php.
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $catalog = config('datecito.catalog');
        $limits = config('datecito.limits');
        $basicsRules = collect($catalog['basics'])
            ->mapWithKeys(fn (array $options, string $key) => ["basics.$key" => ['nullable', 'string', Rule::in($options)]])
            ->all();
        $teamRules = collect($catalog['teams'])
            ->mapWithKeys(fn (array $leagues, string $sport) => [
                "teams.$sport" => ['nullable', 'array', 'max:'.$limits['teams_per_sport']],
                "teams.$sport.*" => ['string', 'distinct', Rule::in(Arr::flatten($leagues))],
            ])
            ->all();

        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:40'],
            'birthdate' => ['sometimes', 'date', 'before_or_equal:'.today()->subYears($limits['min_age'])->toDateString(), 'after:1920-01-01'],
            'gender' => ['sometimes', 'nullable', Rule::in($catalog['genders'])],
            'interested_in' => ['sometimes', 'array', 'max:3'],
            'interested_in.*' => ['string', Rule::in($catalog['interested_in'])],
            'job' => ['sometimes', 'nullable', 'string', 'max:60'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:300'],
            'intent' => ['sometimes', 'nullable', Rule::in($catalog['intents'])],
            'relationship_type' => ['sometimes', 'nullable', Rule::in($catalog['relationship_types'])],
            'zone' => ['sometimes', 'nullable', 'string', 'max:60'],
            'interests' => ['sometimes', 'array', 'max:'.$limits['interests_max']],
            'interests.*' => ['string', 'distinct', Rule::in(Arr::flatten($catalog['interests']))],
            'music_genres' => ['sometimes', 'array', 'max:'.$limits['genres_max']],
            'music_genres.*' => ['string', 'distinct', Rule::in($catalog['genres'])],
            'music_artists' => ['sometimes', 'array', 'max:5'],
            'music_artists.*' => ['string', 'max:60'],
            'anthem' => ['sometimes', 'nullable', 'string', 'max:120'],
            'basics' => ['sometimes', 'array:'.implode(',', array_keys($catalog['basics']))],
            ...$basicsRules,
            'teams' => ['sometimes', 'array:'.implode(',', array_keys($catalog['teams']))],
            ...$teamRules,
            'languages' => ['sometimes', 'array', 'max:8'],
            'languages.*' => ['string', 'distinct', Rule::in($catalog['languages'])],
            'prompts' => ['sometimes', 'array', 'max:'.$limits['prompts_max']],
            'prompts.*.q' => ['required', 'string', 'distinct', Rule::in($catalog['prompts'])],
            'prompts.*.a' => ['required', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function profileData(): array
    {
        $data = $this->validated();

        if (isset($data['basics'])) {
            $data['basics'] = array_filter($data['basics'], fn ($value) => $value !== null);
        }

        if (isset($data['teams'])) {
            $data['teams'] = array_filter($data['teams'], fn (?array $teams) => ! empty($teams));
        }

        if (isset($data['prompts'])) {
            $data['prompts'] = array_map(fn (array $p) => ['q' => $p['q'], 'a' => $p['a']], $data['prompts']);
        }

        return $data;
    }
}
