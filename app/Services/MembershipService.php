<?php

namespace App\Services;

use App\Contracts\PurchaseVerifier;
use App\Enums\PurchaseStore;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Membresía única (mensual o anual), periodo gratuito de lanzamiento y Super likes.
 */
class MembershipService
{
    public function __construct(private PurchaseVerifier $verifier) {}

    public function hasAccess(User $user): bool
    {
        return config('datecito.launch_free') || $this->currentSubscription($user) !== null;
    }

    public function currentSubscription(User $user): ?Subscription
    {
        return $user->subscriptions()
            ->where('status', SubscriptionStatus::Active)
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();
    }

    /**
     * @return array{status: string, launch_free: bool, plan: ?string, expires_at: ?string, super_likes: array{free_today: int, purchased: int, next_free_at: ?string, every_days: int}, pricing: array<string, mixed>}
     */
    public function summary(User $user): array
    {
        $subscription = $this->currentSubscription($user);

        return [
            'status' => match (true) {
                $subscription !== null => 'active',
                (bool) config('datecito.launch_free') => 'launch',
                default => 'inactive',
            },
            'launch_free' => (bool) config('datecito.launch_free'),
            'plan' => $subscription?->plan->value,
            'expires_at' => $subscription?->expires_at->toIso8601String(),
            'super_likes' => [
                'free_today' => $this->freeSuperLikesLeft($user),
                'purchased' => $user->super_likes_balance,
                'next_free_at' => $this->nextFreeSuperLikeAt($user)?->toIso8601String(),
                'every_days' => (int) config('datecito.limits.super_like_every_days'),
            ],
            'pricing' => config('datecito.pricing'),
        ];
    }

    /**
     * Hay un Super like gratis cada N días (config limits.super_like_every_days).
     */
    public function freeSuperLikesLeft(User $user): int
    {
        return $this->nextFreeSuperLikeAt($user) === null ? 1 : 0;
    }

    /**
     * Cuándo vuelve a tener Super like gratis; null si ya lo tiene.
     */
    public function nextFreeSuperLikeAt(User $user): ?CarbonInterface
    {
        $next = $user->free_super_like_at?->addDays((int) config('datecito.limits.super_like_every_days'));

        return $next !== null && $next->isFuture() ? $next : null;
    }

    /**
     * Gasta un Super like: primero el gratuito del día, luego los comprados.
     * Debe llamarse dentro de una transacción.
     */
    public function spendSuperLike(User $user): void
    {
        // Serializa Super likes simultáneos de la misma persona.
        User::query()->whereKey($user->id)->lockForUpdate()->first();

        if ($this->freeSuperLikesLeft($user) > 0) {
            $user->forceFill(['free_super_like_at' => now()])->save();

            return;
        }

        $affected = User::query()
            ->whereKey($user->id)
            ->where('super_likes_balance', '>', 0)
            ->decrement('super_likes_balance');

        if ($affected === 0) {
            throw new DomainRuleException('no_super_likes', 'Ya usaste tu Super like gratis. Puedes comprar más.', 402);
        }
    }

    public function purchase(User $user, PurchaseStore $store, string $productId, string $purchaseToken): void
    {
        $pricing = config('datecito.pricing');
        $verified = $this->verifier->verify($store, $productId, $purchaseToken);

        DB::transaction(function () use ($user, $store, $verified, $pricing): void {
            if (in_array($verified->productId, [$pricing['monthly']['product_id'], $pricing['yearly']['product_id']], true)) {
                if (Subscription::query()->where('store_reference', $verified->storeReference)->exists()) {
                    return;
                }

                $user->subscriptions()->create([
                    'plan' => $verified->productId === $pricing['yearly']['product_id'] ? SubscriptionPlan::Yearly : SubscriptionPlan::Monthly,
                    'store' => $store,
                    'store_reference' => $verified->storeReference,
                    'status' => SubscriptionStatus::Active,
                    'starts_at' => now(),
                    'expires_at' => $verified->expiresAt,
                ]);

                return;
            }

            if ($verified->productId === $pricing['super_like_pack']['product_id']) {
                if (Purchase::query()->where('store_reference', $verified->storeReference)->exists()) {
                    return;
                }

                $quantity = (int) $pricing['super_like_pack']['quantity'];
                (new Purchase([
                    'product_id' => $verified->productId,
                    'store' => $store,
                    'store_reference' => $verified->storeReference,
                    'quantity' => $quantity,
                ]))->forceFill(['user_id' => $user->id])->save();
                User::query()->whereKey($user->id)->increment('super_likes_balance', $quantity);

                return;
            }

            throw new DomainRuleException('unknown_product', 'Producto desconocido.');
        });
    }
}
