<?php

namespace App\Http\Requests\Me;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sólo imágenes reales (el tipo se detecta por el contenido, no por la extensión).
 */
class StorePhotoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288', 'dimensions:min_width=320,min_height=320,max_width=5000,max_height=5000'],
        ];
    }
}
