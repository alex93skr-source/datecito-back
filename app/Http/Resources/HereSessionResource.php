<?php

namespace App\Http\Resources;

use App\Models\HereSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sesión propia de "Aquí y ahora". Nunca devuelve coordenadas.
 *
 * @mixin HereSession
 */
class HereSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'place' => $this->place,
            'verified_only' => $this->verified_only,
            'ends_at' => $this->ends_at->toIso8601String(),
        ];
    }
}
