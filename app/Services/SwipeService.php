<?php

namespace App\Services;

use App\Enums\ConnectionSource;
use App\Enums\SwipeType;
use App\Exceptions\DomainRuleException;
use App\Models\Connection;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Likes, "No me interesa" y Super likes. Crea la conexión cuando el like es mutuo.
 */
class SwipeService
{
    public function __construct(
        private MembershipService $membership,
        private ConnectionService $connections,
        private DailyPacingService $pacing,
    ) {}

    /**
     * @return array{matched: bool, connection: ?Connection}
     */
    public function swipe(User $viewer, User $target, SwipeType $type): array
    {
        if ($viewer->is($target) || ! $target->isActive() || ! $target->hasVerifiedEmail() || $viewer->hasBlockWith($target)) {
            throw new DomainRuleException('person_unavailable', 'Este perfil ya no está disponible.', 404);
        }

        return DB::transaction(function () use ($viewer, $target, $type): array {
            $existing = Swipe::query()->where('user_id', $viewer->id)->where('target_id', $target->id)->lockForUpdate()->first();
            // Segunda oportunidad: un "No me interesa" viejo deja que la persona vuelva a decidir.
            $secondChance = $existing?->type === SwipeType::Pass
                && $existing->created_at->isBefore(now()->subDays((int) config('datecito.limits.pass_returns_after_days')));

            if ($existing !== null && ! $secondChance) {
                $connection = Connection::between($viewer, $target);

                return ['matched' => $connection !== null, 'connection' => $connection];
            }

            $likedBack = Swipe::query()
                ->where('user_id', $target->id)
                ->where('target_id', $viewer->id)
                ->whereIn('type', [SwipeType::Like, SwipeType::Super])
                ->exists();

            // Corresponder un like no gasta el límite diario: la otra persona ya decidió.
            if ($type === SwipeType::Like && ! $likedBack && $this->pacing->likesLeft($viewer) === 0) {
                throw new DomainRuleException('no_likes_left', 'Ya usaste tus '.config('datecito.limits.likes_per_day').' likes de hoy. Vuelven a medianoche.', 403);
            }

            if ($type === SwipeType::Super) {
                $this->membership->spendSuperLike($viewer);
            }

            if ($secondChance) {
                $existing->forceFill(['type' => $type, 'created_at' => now()])->save();
            } else {
                $viewer->swipes()->create(['target_id' => $target->id, 'type' => $type]);
            }

            if ($type === SwipeType::Pass) {
                return ['matched' => false, 'connection' => null];
            }

            if (! $likedBack) {
                return ['matched' => false, 'connection' => null];
            }

            return ['matched' => true, 'connection' => $this->connections->open($viewer, $target, ConnectionSource::Discover)];
        });
    }
}
