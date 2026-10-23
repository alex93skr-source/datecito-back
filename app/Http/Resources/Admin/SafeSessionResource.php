<?php

namespace App\Http\Resources\Admin;

use App\Models\SafeSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sesión de Datecito Seguro vista por el equipo (sin contactos ni ubicación).
 *
 * Espera with(user, datecito.connection.userOne, datecito.connection.userTwo).
 *
 * @mixin SafeSession
 */
class SafeSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => UserSummaryResource::mini($this->user),
            'datecito' => DatecitoResource::make($this->datecito),
            'status' => $this->status->value,
            'check_in_at' => $this->check_in_at->toIso8601String(),
            'help_requested_at' => $this->help_requested_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'share_location' => $this->share_location,
            'contacts_count' => count($this->contact_ids ?? []),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
