<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\DiscoveryService;
use App\Services\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Perfil completo de la persona autenticada (sólo lo ve ella misma).
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'email_verified' => $this->hasVerifiedEmail(),
            'name' => $this->name,
            'birthdate' => $this->birthdate?->toDateString(),
            'age' => $this->age(),
            'gender' => $this->gender,
            'interested_in' => $this->interested_in ?? [],
            'job' => $this->job,
            'bio' => $this->bio,
            'intent' => $this->intent,
            'relationship_type' => $this->relationship_type,
            'zone' => $this->zone,
            'has_location' => $this->approx_lat !== null,
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
            'photos' => PhotoResource::collection($this->whenLoaded('photos')),
            'settings' => [
                'show_in_discover' => $this->show_in_discover,
                'show_gender' => $this->show_gender,
                'show_job' => $this->show_job,
                'show_relationship' => $this->show_relationship,
                'only_verified_messages' => $this->only_verified_messages,
                'i_write_first' => $this->i_write_first,
                'offensive_warning' => $this->offensive_warning,
            ],
            'filters' => app(DiscoveryService::class)->filters($this->resource),
            'verification' => [
                'status' => $this->verification_status->value,
                'verified' => $this->isVerified(),
            ],
            'membership' => app(MembershipService::class)->summary($this->resource),
            'profile_completion' => $this->completion(),
            'is_moderator' => $this->canModerate(),
        ];
    }

    private function completion(): int
    {
        $checks = [
            filled($this->name), filled($this->birthdate), filled($this->bio), filled($this->job), filled($this->intent),
            count($this->interests ?? []) >= 3, count($this->music_genres ?? []) > 0, filled($this->anthem),
            count($this->basics ?? []) >= 3, count($this->languages ?? []) > 0, count($this->prompts ?? []) >= 2,
            $this->relationLoaded('photos') && $this->photos->count() >= 3, $this->isVerified(),
        ];

        return (int) round(100 * count(array_filter($checks)) / count($checks));
    }
}
