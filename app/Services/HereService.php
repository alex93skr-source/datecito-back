<?php

namespace App\Services;

use App\Enums\ConnectionSource;
use App\Exceptions\DomainRuleException;
use App\Models\Connection;
use App\Models\HereGreet;
use App\Models\HereHidden;
use App\Models\HereSession;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Aquí y ahora": conocer a quien está en el mismo lugar, en este momento.
 *
 * Privacidad: coordenadas redondeadas (~100 m), nunca se devuelven ni se
 * muestra distancia; sólo se ven quienes también activaron el modo en el
 * mismo lugar; la sesión caduca sola y no se guarda historial de lugares.
 */
class HereService
{
    public function __construct(private ConnectionService $connections) {}

    public function current(User $user): ?HereSession
    {
        return HereSession::query()->where('user_id', $user->id)->live()->latest()->first();
    }

    public function start(User $user, string $place, float $lat, float $lng, int $minutes, bool $verifiedOnly): HereSession
    {
        return DB::transaction(function () use ($user, $place, $lat, $lng, $minutes, $verifiedOnly): HereSession {
            HereSession::query()->where('user_id', $user->id)->delete();

            $session = new HereSession([
                'place' => $place,
                'lat' => Geo::approximate($lat, 3),
                'lng' => Geo::approximate($lng, 3),
                'verified_only' => $verifiedOnly,
                'ends_at' => now()->addMinutes($minutes),
            ]);
            $session->forceFill(['user_id' => $user->id])->save();

            return $session;
        });
    }

    public function stop(User $user): void
    {
        HereSession::query()->where('user_id', $user->id)->delete();
    }

    /**
     * @return Collection<int, User>
     */
    public function peopleNear(User $viewer): Collection
    {
        $mine = $this->current($viewer);

        if ($mine === null) {
            throw new DomainRuleException('here_inactive', 'Activa Aquí y ahora para ver quién está en el lugar.');
        }

        $radiusKm = config('datecito.limits.here_radius_meters') / 1000;
        $delta = 0.002 + $radiusKm / 111.0;
        $hidden = HereHidden::query()->where('user_id', $viewer->id)->pluck('hidden_user_id')
            ->merge(HereHidden::query()->where('hidden_user_id', $viewer->id)->pluck('user_id'));
        $excluded = $hidden->merge($viewer->blockedUserIds())->push($viewer->id)->unique()->all();

        return HereSession::query()
            ->live()
            ->whereBetween('lat', [$mine->lat - $delta, $mine->lat + $delta])
            ->whereBetween('lng', [$mine->lng - $delta, $mine->lng + $delta])
            ->whereNotIn('user_id', $excluded)
            ->when(! $viewer->isVerified(), fn ($q) => $q->where('verified_only', false))
            ->with('user.photos')
            ->get()
            ->filter(fn (HereSession $s) => Geo::distanceKm($mine->lat, $mine->lng, $s->lat, $s->lng) <= $radiusKm)
            ->pluck('user')
            ->filter(fn (?User $u) => $u !== null && $u->isActive() && $u->hasVerifiedEmail())
            ->values();
    }

    /**
     * @return array{matched: bool, connection: ?Connection}
     */
    public function greet(User $viewer, User $target): array
    {
        $visible = $this->peopleNear($viewer)->contains(fn (User $u) => $u->is($target));

        if (! $visible) {
            throw new DomainRuleException('person_unavailable', 'Esta persona ya no está en Aquí y ahora.', 404);
        }

        return DB::transaction(function () use ($viewer, $target): array {
            HereGreet::query()->firstOrCreate(['user_id' => $viewer->id, 'target_id' => $target->id]);

            $mutual = HereGreet::query()->where('user_id', $target->id)->where('target_id', $viewer->id)->exists();

            if (! $mutual) {
                return ['matched' => false, 'connection' => null];
            }

            return ['matched' => true, 'connection' => $this->connections->open($viewer, $target, ConnectionSource::Here)];
        });
    }

    public function hide(User $viewer, User $target): void
    {
        HereHidden::query()->firstOrCreate(['user_id' => $viewer->id, 'hidden_user_id' => $target->id]);
    }

    /**
     * @return list<string>
     */
    public function greetedIds(User $viewer): array
    {
        return HereGreet::query()->where('user_id', $viewer->id)->pluck('target_id')->all();
    }

    /**
     * Borra sesiones caducadas (lo ejecuta el scheduler).
     */
    public function purgeExpired(): int
    {
        return HereSession::query()->where('ends_at', '<=', now())->delete();
    }
}
