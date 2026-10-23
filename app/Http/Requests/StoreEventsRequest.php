<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Lote de eventos de comportamiento enviados por la app (pantallas vistas, acciones de interfaz).
 */
class StoreEventsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:50'],
            'events.*.name' => ['required', 'string', 'regex:/^[a-z][a-z0-9_.\-]{1,79}$/'],
            'events.*.properties' => ['nullable', 'array', 'max:20'],
            'events.*.properties.*' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_scalar($value) || (is_string($value) && mb_strlen($value) > 200)) {
                    $fail('Cada propiedad debe ser un texto corto, número o booleano.');
                }
            }],
            'events.*.occurred_at' => ['nullable', 'date', 'after:-7 days', 'before:+5 minutes'],
        ];
    }

    /**
     * @return list<array{name: string, properties: array<string, mixed>|null, occurred_at: string|null}>
     */
    public function events(): array
    {
        return array_map(fn (array $event) => [
            'name' => $event['name'],
            'properties' => $event['properties'] ?? null,
            'occurred_at' => $event['occurred_at'] ?? null,
        ], $this->validated('events'));
    }
}
