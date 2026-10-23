<?php

namespace App\Http\Resources\Admin;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mensaje descifrado para revisión de moderación. Cada lectura queda en la bitácora.
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
            'sender_id' => $this->sender_id,
            'body' => $this->body,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
