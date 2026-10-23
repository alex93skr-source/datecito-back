<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\PhotoResource;
use App\Models\User;
use App\Services\Admin\UserInsights;
use App\Services\MembershipService;
use Illuminate\Http\Request;

/**
 * Ficha completa de una persona para el CRM: perfil, estadísticas, membresía, notas y etiquetas.
 *
 * Espera with(photos, tags, notes.author, subscriptions, purchases, verificationRequests.reviewer).
 *
 * @mixin User
 */
class UserDetailResource extends UserSummaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $membership = app(MembershipService::class)->summary($this->resource);
        unset($membership['pricing']);

        return [
            ...parent::toArray($request),
            'profile' => [
                'birthdate' => $this->birthdate?->toDateString(),
                'interested_in' => $this->interested_in ?? [],
                'job' => $this->job,
                'bio' => $this->bio,
                'intent' => $this->intent,
                'relationship_type' => $this->relationship_type,
                'interests' => $this->interests ?? [],
                'music' => ['genres' => $this->music_genres ?? [], 'artists' => $this->music_artists ?? [], 'anthem' => $this->anthem],
                'basics' => (object) ($this->basics ?? []),
                'languages' => $this->languages ?? [],
                'prompts' => $this->prompts ?? [],
                'has_location' => $this->approx_lat !== null,
            ],
            'settings' => [
                'show_in_discover' => $this->show_in_discover,
                'show_gender' => $this->show_gender,
                'show_job' => $this->show_job,
                'show_relationship' => $this->show_relationship,
                'only_verified_messages' => $this->only_verified_messages,
                'i_write_first' => $this->i_write_first,
                'offensive_warning' => $this->offensive_warning,
            ],
            'photos' => PhotoResource::collection($this->whenLoaded('photos')),
            'membership' => $membership,
            'subscriptions' => SubscriptionResource::collection($this->whenLoaded('subscriptions')),
            'purchases' => PurchaseResource::collection($this->whenLoaded('purchases')),
            'verification_requests' => VerificationResource::collection($this->whenLoaded('verificationRequests')),
            'notes' => UserNoteResource::collection($this->whenLoaded('notes')),
            'stats' => app(UserInsights::class)->stats($this->resource),
            'signup_platform' => $this->signup_platform,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
