<?php

namespace App\Http\Resources\Admin;

use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Purchase
 */
class PurchaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => UserSummaryResource::mini($this->user)),
            'product_id' => $this->product_id,
            'store' => $this->store->value,
            'store_reference' => $this->store_reference,
            'quantity' => $this->quantity,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
