<?php

namespace App\Http\Resources\Admin;

use App\Models\ActivityEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ActivityEvent
 */
class ActivityEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => UserSummaryResource::mini($this->user)),
            'name' => $this->name,
            'category' => $this->category,
            'properties' => (object) ($this->properties ?? []),
            'platform' => $this->platform,
            'app_version' => $this->app_version,
            'status' => $this->status,
            'duration_ms' => $this->duration_ms,
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
