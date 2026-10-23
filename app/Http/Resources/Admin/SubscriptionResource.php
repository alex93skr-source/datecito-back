<?php

namespace App\Http\Resources\Admin;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => UserSummaryResource::mini($this->user)),
            'plan' => $this->plan->value,
            'store' => $this->store->value,
            'store_reference' => $this->store_reference,
            'granted' => str_starts_with($this->store_reference, 'crm:'),
            'status' => $this->status->value,
            'current' => $this->isCurrent(),
            'starts_at' => $this->starts_at->toIso8601String(),
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
