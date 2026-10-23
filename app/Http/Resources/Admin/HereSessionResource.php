<?php

namespace App\Http\Resources\Admin;

use App\Models\HereSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sesión de "Aquí y ahora" vista por el equipo. Nunca devuelve coordenadas.
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
            'user' => UserSummaryResource::mini($this->user),
            'place' => $this->place,
            'verified_only' => $this->verified_only,
            'ends_at' => $this->ends_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
