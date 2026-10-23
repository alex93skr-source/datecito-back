<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DatecitoFeedbackRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'showed' => ['required', Rule::in(['Sí', 'Llegó tarde', 'No llegó'])],
            'photos' => ['required', Rule::in(['Sí', 'Más o menos', 'No'])],
            'safe' => ['required', Rule::in(['Sí, todo bien', 'No del todo', 'No'])],
            'again' => ['required', Rule::in(['Sí', 'Tal vez', 'No'])],
        ];
    }
}
