<?php

namespace App\Http\Resources;

use App\Enums\DatecitoStatus;
use App\Models\Datecito;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Datecito
 */
class DatecitoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $me = $request->user();

        return [
            'id' => $this->id,
            'connection_id' => $this->connection_id,
            'person' => PersonResource::make($this->connection->other($me)),
            'kind' => $this->kind,
            'place' => $this->place,
            'zone' => $this->zone,
            'when_label' => $this->when_label,
            'scheduled_on' => $this->scheduled_on?->toDateString(),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'note' => $this->note,
            'status' => $this->status->value,
            'proposed_by_me' => $this->proposer_id === $me->id,
            'can_confirm' => $this->status === DatecitoStatus::Proposed && $this->proposer_id !== $me->id,
            'is_past' => $this->isPast(),
            'feedback_sent' => $this->feedback->contains('user_id', $me->id),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
