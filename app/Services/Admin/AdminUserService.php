<?php

namespace App\Services\Admin;

use App\Enums\PurchaseStore;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\Photo;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AccountService;
use App\Services\MembershipService;
use App\Services\PhotoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Acciones del equipo sobre una cuenta. Todas quedan en la bitácora.
 */
class AdminUserService
{
    public function __construct(
        private AuditLog $audit,
        private MembershipService $membership,
        private PhotoService $photos,
        private AccountService $accounts,
    ) {}

    public function setStatus(User $user, UserStatus $status, ?string $note = null): void
    {
        $previous = $user->status;
        $user->forceFill(['status' => $status])->save();

        if (in_array($status, [UserStatus::Suspended, UserStatus::Banned], true)) {
            $user->tokens()->delete();
        }

        $this->audit->recordOnUser('user.status', $user, ['from' => $previous->value, 'to' => $status->value], $note);
    }

    public function setRole(User $user, UserRole $role, ?string $note = null): void
    {
        $previous = $user->role;
        $user->forceFill(['role' => $role])->save();

        $this->audit->recordOnUser('user.role', $user, ['from' => $previous->value, 'to' => $role->value], $note);
    }

    /**
     * Verificación manual (o revocación) del perfil sin selfie de por medio.
     */
    public function setVerified(User $user, bool $verified, ?string $note = null): void
    {
        $user->forceFill([
            'verification_status' => $verified ? VerificationStatus::Approved : VerificationStatus::None,
            'verified_at' => $verified ? now() : null,
        ])->save();

        $this->audit->recordOnUser($verified ? 'user.verified' : 'user.unverified', $user, null, $note);
    }

    public function adjustSuperLikes(User $user, int $delta, ?string $note = null): int
    {
        $balance = DB::transaction(function () use ($user, $delta): int {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $balance = max(0, $locked->super_likes_balance + $delta);
            $locked->forceFill(['super_likes_balance' => $balance])->save();

            return $balance;
        });

        $this->audit->recordOnUser('user.super_likes', $user, ['delta' => $delta, 'balance' => $balance], $note);

        return $balance;
    }

    /**
     * Regala una membresía (cortesía) por N días.
     */
    public function grantMembership(User $user, SubscriptionPlan $plan, int $days, ?string $note = null): Subscription
    {
        $subscription = $user->subscriptions()->create([
            'plan' => $plan,
            'store' => PurchaseStore::Fake,
            'store_reference' => 'crm:'.Str::ulid(),
            'status' => SubscriptionStatus::Active,
            'starts_at' => now(),
            'expires_at' => now()->addDays($days),
        ]);

        $this->audit->recordOnUser('user.membership_granted', $user, ['plan' => $plan->value, 'days' => $days, 'subscription_id' => $subscription->id], $note);

        return $subscription;
    }

    /**
     * Cancela la membresía vigente (deja de tener acceso al terminar la petición).
     */
    public function revokeMembership(User $user, ?string $note = null): bool
    {
        $current = $this->membership->currentSubscription($user);

        if ($current === null) {
            return false;
        }

        $current->forceFill(['status' => SubscriptionStatus::Cancelled, 'expires_at' => now()])->save();
        $this->audit->recordOnUser('user.membership_revoked', $user, ['subscription_id' => $current->id, 'plan' => $current->plan->value], $note);

        return true;
    }

    public function removePhoto(User $user, Photo $photo, ?string $note = null): void
    {
        $this->photos->delete($photo);
        $this->audit->recordOnUser('user.photo_removed', $user, ['photo_id' => $photo->id], $note);
    }

    public function logoutEverywhere(User $user, ?string $note = null): int
    {
        $count = $user->tokens()->delete();
        $this->audit->recordOnUser('user.logout_all', $user, ['sessions' => $count], $note);

        return $count;
    }

    public function deleteAccount(User $user, ?string $note = null): void
    {
        // La bitácora se escribe primero: después de borrar ya no existe la persona.
        $this->audit->record('user.deleted', $user, ['email' => $user->email, 'name' => $user->name], $note);
        $this->accounts->delete($user, 'admin');
    }
}
