<?php

namespace App\Http\Resources\Admin;

use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Conexión (match) vista por el equipo. Nunca incluye el contenido de los mensajes.
 *
 * Espera with(userOne, userTwo) y withCount(messages, datecitos).
 *
 * @mixin Connection
 */
class ConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source->value,
            'users' => [UserSummaryResource::mini($this->userOne), UserSummaryResource::mini($this->userTwo)],
            'messages_count' => $this->messages_count ?? null,
            'datecitos_count' => $this->datecitos_count ?? null,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'active' => $this->isActive(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'ended_by' => $this->ended_by,
            'datecitos' => DatecitoResource::collection($this->whenLoaded('datecitos')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
