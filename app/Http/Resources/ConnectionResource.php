<?php

namespace App\Http\Resources;

use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin Connection
 */
class ConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $me = $request->user();
        $other = $this->other($me);
        $last = $this->latestMessage;

        return [
            'id' => $this->id,
            'source' => $this->source->value,
            'created_at' => $this->created_at->toIso8601String(),
            'person' => PersonResource::make($other),
            'last_message' => $last === null ? null : [
                'preview' => Str::limit($last->body, 80),
                'from_me' => $last->sender_id === $me->id,
                'created_at' => $last->created_at->toIso8601String(),
            ],
            'has_active_datecito' => $this->datecitos_count > 0,
        ];
    }
}
