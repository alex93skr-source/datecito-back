<?php

namespace App\Http\Resources;

use App\Models\Photo;
use App\Services\PhotoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Photo
 */
class PhotoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'urls' => app(PhotoService::class)->urls($this->resource),
        ];
    }
}
