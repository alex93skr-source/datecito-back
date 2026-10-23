<?php

namespace App\Http\Resources\Admin;

use App\Models\Datecito;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Datecito visto por el equipo. Las respuestas de "¿Cómo te fue?" nunca se exponen.
 *
 * Espera with(connection.userOne, connection.userTwo) y withCount(feedback).
 *
 * @mixin Datecito
 */
class DatecitoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $connection = $this->whenLoaded('connection');

        return [
            'id' => $this->id,
            'connection_id' => $this->connection_id,
            'proposer_id' => $this->proposer_id,
            'users' => $connection ? [UserSummaryResource::mini($connection->userOne), UserSummaryResource::mini($connection->userTwo)] : [],
            'kind' => $this->kind,
            'place' => $this->place,
            'zone' => $this->zone,
            'when_label' => $this->when_label,
            'scheduled_on' => $this->scheduled_on?->toDateString(),
            'note' => $this->note,
            'status' => $this->status->value,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'feedback_count' => $this->feedback_count ?? null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
