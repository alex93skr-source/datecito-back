<?php

namespace App\Http\Resources\Admin;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reporte visto por moderación. El detalle sólo se descifra aquí.
 *
 * @mixin Report
 */
class ReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'details' => $this->details,
            'status' => $this->status->value,
            'resolution_note' => $this->resolution_note,
            'reporter' => ['id' => $this->reporter_id, 'name' => $this->reporter?->name, 'email' => $this->reporter?->email],
            'reported' => $this->reported === null ? null : [
                'id' => $this->reported->id,
                'name' => $this->reported->name,
                'email' => $this->reported->email,
                'status' => $this->reported->status->value,
                'verified' => $this->reported->isVerified(),
                'reports_received' => $this->reported->reports_received_count ?? null,
            ],
            'handled_by' => $this->whenLoaded('handler', fn () => UserSummaryResource::mini($this->handler)),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
