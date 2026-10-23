<?php

namespace App\Http\Resources\Admin;

use App\Models\WebMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebMessage
 */
class WebMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folio' => $this->folio(),
            'kind' => $this->kind,
            'lang' => $this->lang,
            'name' => $this->name,
            'email' => $this->email,
            'topic' => $this->topic,
            'company' => $this->company,
            'city' => $this->city,
            'message' => $this->message,
            'has_attachment' => $this->attachment_key !== null,
            'status' => $this->status,
            'handled_by' => $this->whenLoaded('handler', fn () => $this->handler ? UserSummaryResource::mini($this->handler) : null),
            'handled_at' => $this->handled_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
