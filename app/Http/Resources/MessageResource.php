<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mensaje sin estado de lectura ni de entrega: así no hay presión por responder.
 *
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'from_me' => $this->sender_id === $request->user()->id,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
