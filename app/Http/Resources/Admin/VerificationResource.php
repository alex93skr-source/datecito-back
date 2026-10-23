<?php

namespace App\Http\Resources\Admin;

use App\Models\VerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Solicitud de verificación con selfie. Los enlaces son temporales y sólo se generan para el equipo.
 *
 * Espera with(user.photos, reviewer).
 *
 * @mixin VerificationRequest
 */
class VerificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $disk = Storage::disk(config('datecito.photos.signing_disk'));
        $expires = now()->addMinutes(10);
        $user = $this->whenLoaded('user');
        $photo = $user && $user->relationLoaded('photos') ? $user->photos->first() : null;

        return [
            'id' => $this->id,
            'user' => $user ? UserSummaryResource::mini($user) : null,
            'status' => $this->status->value,
            'pose' => $this->pose,
            'selfie_url' => $disk->temporaryUrl($this->selfie_key, $expires),
            'profile_photo_url' => $photo === null ? null : $disk->temporaryUrl($photo->storage_key.'/md.webp', $expires),
            'reviewed_by' => $this->whenLoaded('reviewer', fn () => UserSummaryResource::mini($this->reviewer)),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
