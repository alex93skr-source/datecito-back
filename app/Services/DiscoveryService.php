<?php

namespace App\Services;

use App\Enums\SwipeType;
use App\Models\Connection;
use App\Models\Swipe;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Arma el mazo de Descubrir.
 *
 * Filtros en modo "Sólo" se aplican en la consulta; en modo "Prefiero" sólo
 * ordenan (nadie se oculta). No existen filtros por apariencia física.
 */
class DiscoveryService
{
    private const CANDIDATE_POOL = 200;

    /**
     * Mapea "me interesa conocer a" con el género declarado.
     */
    private const AUDIENCE_TO_GENDER = ['Mujeres' => 'Mujer', 'Hombres' => 'Hombre'];

    public function __construct(private DailyPacingService $pacing) {}

    /**
     * @return Collection<int, User>
     */
    public function deck(User $viewer): Collection
    {
        // "Tus personas de hoy": nunca más que los perfiles que le quedan en el día.
        $batch = min((int) config('datecito.limits.discover_batch'), $this->pacing->profilesLeft($viewer));

        if ($batch === 0) {
            return new Collection;
        }

        $filters = $this->filters($viewer);
        $query = $this->baseQuery($viewer, $filters);

        $candidates = $query->with('photos')->limit(self::CANDIDATE_POOL)->get();
        $superLikedMe = Swipe::query()
            ->where('target_id', $viewer->id)
            ->where('type', SwipeType::Super)
            ->whereIn('user_id', $candidates->modelKeys())
            ->pluck('user_id')
            ->flip();

        return $candidates
            ->sortByDesc(fn (User $candidate) => $this->score($viewer, $candidate, $filters, $superLikedMe->has($candidate->id)))
            ->take($batch)
            // Para mostrar "Te dio un Super like" en la tarjeta (no se guarda).
            ->each(fn (User $candidate) => $candidate->setAttribute('super_liked_me', $superLikedMe->has($candidate->id)))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<User>
     */
    private function baseQuery(User $viewer, array $filters): Builder
    {
        $query = User::query()
            ->reachable()
            ->where('show_in_discover', true)
            ->whereKeyNot($viewer->id)
            ->whereHas('photos')
            // Quien pasaste de largo vuelve después de unos días (segunda oportunidad).
            ->whereNotIn('id', Swipe::query()->select('target_id')->where('user_id', $viewer->id)->where(fn (Builder $q) => $q
                ->where('type', '!=', SwipeType::Pass)
                ->orWhere('created_at', '>=', now()->subDays((int) config('datecito.limits.pass_returns_after_days')))))
            ->whereNotIn('id', Connection::query()->select('user_two_id')->where('user_one_id', $viewer->id))
            ->whereNotIn('id', Connection::query()->select('user_one_id')->where('user_two_id', $viewer->id))
            ->whereNotIn('id', $viewer->blockedUserIds());

        $query->whereBetween('birthdate', [
            today()->subYears($filters['maxAge'] + 1)->addDay(),
            today()->subYears($filters['minAge']),
        ]);

        $this->applyAudience($query, $viewer, $filters['meet']);
        $this->applyDistance($query, $viewer, (int) $filters['km']);

        if ($filters['verifiedOnly']) {
            $query->whereNotNull('verified_at');
        }

        // Sólo la intención excluye; el resto de preferencias únicamente ordena (ver score()).
        if (! empty($filters['intents'])) {
            $query->whereIn('intent', $filters['intents']);
        }

        return $query;
    }

    /**
     * Respeta lo que busca quien mira y lo que busca quien aparece.
     *
     * @param  Builder<User>  $query
     */
    private function applyAudience(Builder $query, User $viewer, string $meet): void
    {
        if (isset(self::AUDIENCE_TO_GENDER[$meet])) {
            $query->where('gender', self::AUDIENCE_TO_GENDER[$meet]);
        }

        $viewerAudience = array_search($viewer->gender, self::AUDIENCE_TO_GENDER, true);

        $query->where(function (Builder $q) use ($viewerAudience): void {
            $q->whereNull('interested_in')->orWhereJsonContains('interested_in', 'Todas las personas');

            if ($viewerAudience !== false) {
                $q->orWhereJsonContains('interested_in', $viewerAudience);
            }
        });
    }

    /**
     * Pre-filtro rectangular barato; la distancia exacta no se calcula ni se expone.
     *
     * @param  Builder<User>  $query
     */
    private function applyDistance(Builder $query, User $viewer, int $km): void
    {
        if ($viewer->approx_lat === null || $viewer->approx_lng === null) {
            return;
        }

        $latDelta = $km / 111.0;
        $lngDelta = $km / max(1.0, 111.0 * cos(deg2rad($viewer->approx_lat)));

        $query->where(function (Builder $q) use ($viewer, $latDelta, $lngDelta): void {
            $q->whereNull('approx_lat')->orWhere(fn (Builder $inner) => $inner
                ->whereBetween('approx_lat', [$viewer->approx_lat - $latDelta, $viewer->approx_lat + $latDelta])
                ->whereBetween('approx_lng', [$viewer->approx_lng - $lngDelta, $viewer->approx_lng + $lngDelta]));
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function score(User $viewer, User $candidate, array $filters, bool $superLikedMe): float
    {
        $overlap = fn (?array $a, ?array $b) => count(array_intersect($a ?? [], $b ?? []));

        $score = $superLikedMe ? 100 : 0;
        $score += 5 * $overlap($viewer->interests, $candidate->interests);
        $score += 2 * $overlap($viewer->music_genres, $candidate->music_genres);
        $score += 4 * $overlap(Arr::flatten($viewer->teams ?? []), Arr::flatten($candidate->teams ?? []));
        $score += $candidate->isVerified() ? 2 : 0;

        // Filtros en modo "Prefiero": suben en la lista, no excluyen.
        $score += 3 * $overlap($filters['interests'], $candidate->interests);
        $score += 3 * $overlap($filters['genres'], $candidate->music_genres);
        $score += 3 * $overlap($filters['languages'], $candidate->languages);
        $score += 3 * $overlap($filters['lifestyle'], array_values($candidate->basics ?? []));
        $score += in_array($candidate->intent, $filters['intents'], true) ? 4 : 0;
        $score += in_array($candidate->relationship_type, $filters['relationships'], true) ? 4 : 0;

        $km = Geo::displayKm($viewer->approx_lat, $viewer->approx_lng, $candidate->approx_lat, $candidate->approx_lng);
        $score -= $km !== null ? min(10, $km / 3) : 0;

        return $score + mt_rand(0, 100) / 100;
    }

    /**
     * Filtros guardados con valores por defecto.
     *
     * @return array{meet: string, minAge: int, maxAge: int, km: int, intents: list<string>, relationships: list<string>, interests: list<string>, genres: list<string>, lifestyle: list<string>, languages: list<string>, verifiedOnly: bool, modes: array<string, string>}
     */
    public function filters(User $user): array
    {
        $defaults = [
            'meet' => 'Todas las personas',
            'minAge' => 18,
            'maxAge' => 60,
            'km' => 25,
            'intents' => [],
            'relationships' => [],
            'interests' => [],
            'genres' => [],
            'lifestyle' => [],
            'languages' => [],
            'verifiedOnly' => false,
            'modes' => [],
        ];

        return array_merge($defaults, $user->filters ?? []);
    }
}
