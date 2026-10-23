<?php

namespace App\Http\Resources\Admin;

use App\Models\Subscription;
use App\Models\User;
use App\Models\UserTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila de persona en el CRM (listados). Incluye correo y estado: sólo para el equipo.
 *
 * Espera withCount(photos, swipes, reportsReceived) y with(tags, subscriptions vigentes).
 *
 * @mixin User
 */
class UserSummaryResource extends JsonResource
{
    /**
     * Referencia mínima a una persona, para anidar en otros recursos.
     *
     * @return array{id: string, name: ?string, email: ?string, status: ?string}|null
     */
    public static function mini(?User $user): ?array
    {
        return $user === null ? null : [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status?->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ?Subscription $subscription */
        $subscription = $this->whenLoaded('subscriptions', fn () => $this->subscriptions->first(), null);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'age' => $this->age(),
            'gender' => $this->gender,
            'zone' => $this->zone,
            'status' => $this->status->value,
            'role' => $this->role->value,
            'email_verified' => $this->hasVerifiedEmail(),
            'verified' => $this->isVerified(),
            'verification_status' => $this->verification_status->value,
            'show_in_discover' => $this->show_in_discover,
            'photos_count' => $this->photos_count ?? null,
            'swipes_count' => $this->swipes_count ?? null,
            'reports_received_count' => $this->reports_received_count ?? null,
            'super_likes_balance' => $this->super_likes_balance,
            'membership' => $subscription === null ? null : ['plan' => $subscription->plan->value, 'expires_at' => $subscription->expires_at->toIso8601String()],
            'platform' => $this->last_platform,
            'app_version' => $this->last_app_version,
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn (UserTag $tag) => $tag->tag)->values()->all(), []),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
