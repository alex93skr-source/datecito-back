<?php

namespace App\Http\Resources;

use App\Enums\VerificationStatus;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Perfil público de otra persona.
 *
 * Nunca incluye correo, fecha de nacimiento, ubicación, estado de la cuenta,
 * actividad ni última conexión. La distancia se redondea hacia arriba en km.
 *
 * @mixin User
 */
class PersonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'age' => $this->age(),
            'zone' => $this->zone,
            'distance_km' => Geo::displayKm($viewer?->approx_lat, $viewer?->approx_lng, $this->approx_lat, $this->approx_lng),
            'job' => $this->show_job ? $this->job : null,
            'gender' => $this->show_gender ? $this->gender : null,
            'bio' => $this->bio,
            'intent' => $this->intent,
            'relationship_type' => $this->show_relationship ? $this->relationship_type : null,
            'interests' => $this->interests ?? [],
            'teams' => (object) ($this->teams ?? []),
            'music' => [
                'genres' => $this->music_genres ?? [],
                'artists' => $this->music_artists ?? [],
                'anthem' => $this->anthem,
            ],
            'basics' => (object) ($this->basics ?? []),
            'languages' => $this->languages ?? [],
            'prompts' => $this->prompts ?? [],
            'verified' => $this->isVerified(),
            // Ya mandó su selfie y está en revisión (48-72 h): se muestra "En verificación".
            'verification_pending' => $this->verification_status === VerificationStatus::Pending,
            // Sólo lo marca Descubrir; en otras pantallas siempre es false.
            'super_liked_me' => (bool) ($this->resource->getAttributes()['super_liked_me'] ?? false),
            // Sólo lo marca "Quién te dio like".
            'liked_me' => (bool) ($this->resource->getAttributes()['liked_me'] ?? false),
            'photos' => PhotoResource::collection($this->whenLoaded('photos')),
        ];
    }
}
