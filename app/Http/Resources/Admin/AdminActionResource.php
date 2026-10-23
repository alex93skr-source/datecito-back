<?php

namespace App\Http\Resources\Admin;

use App\Models\AdminAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Entrada de la bitácora. `subject` se resuelve en el controlador (nombre de la persona afectada).
 *
 * @mixin AdminAction
 */
class AdminActionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor' => $this->whenLoaded('actor', fn () => UserSummaryResource::mini($this->actor)),
            'action' => $this->action,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'subject' => $this->subject ?? null,
            'changes' => (object) ($this->changes ?? []),
            'note' => $this->note,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
