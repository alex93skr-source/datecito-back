<?php

namespace App\Http\Requests;

use App\Models\WebMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formularios públicos del sitio. Las reglas cambian según el tipo de formulario.
 */
class StoreWebMessageRequest extends FormRequest
{
    public const ARCO_RIGHTS = ['Acceso', 'Rectificación', 'Cancelación', 'Oposición', 'Revocación del consentimiento', 'Limitación de uso o divulgación'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $kind = (string) $this->input('kind');
        $needsMessage = in_array($kind, ['contact', 'partners', 'arco'], true);
        $needsName = in_array($kind, ['contact', 'partners', 'arco'], true);

        return [
            'kind' => ['required', Rule::in(WebMessage::KINDS)],
            'lang' => ['nullable', Rule::in(['es', 'en'])],
            'name' => [$needsName ? 'required' : 'nullable', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:160'],
            'topic' => [$kind === 'arco' ? 'required' : 'nullable', 'string', 'max:80', ...($kind === 'arco' ? [Rule::in(self::ARCO_RIGHTS)] : [])],
            'company' => [$kind === 'partners' ? 'required' : 'nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'message' => [$needsMessage ? 'required' : 'nullable', 'string', 'min:10', 'max:4000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            'accepted' => [in_array($kind, ['contact', 'newsletter', 'arco'], true) ? 'accepted' : 'nullable'],
            // Trampa anti-bots: campo invisible que una persona nunca llena.
            'website' => ['nullable', 'max:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }
}
