<?php

namespace App\Http\Resources\Admin;

use App\Models\UserNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserNote
 */
class UserNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'pinned' => $this->pinned,
            'author' => $this->whenLoaded('author', fn () => UserSummaryResource::mini($this->author)),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
