<?php

namespace App\Http\Resources;

use App\Models\SafeSession;
use App\Services\SafeSessionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SafeSession
 */
class SafeSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'datecito' => DatecitoResource::make($this->datecito),
            'contacts' => TrustedContactResource::collection($user->trustedContacts()->whereKey($this->contact_ids)->get()),
            'check_in_at' => $this->check_in_at->toIso8601String(),
            'share_location' => $this->share_location,
            'status' => $this->status->value,
            'share_message' => app(SafeSessionService::class)->shareMessage($this->resource, $user),
        ];
    }
}
