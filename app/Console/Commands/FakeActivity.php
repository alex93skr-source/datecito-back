<?php

namespace App\Console\Commands;

use App\Enums\ConnectionSource;
use App\Enums\DatecitoStatus;
use App\Enums\PurchaseStore;
use App\Enums\ReportStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Enums\SwipeType;
use App\Models\ActivityEvent;
use App\Models\Connection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SOLO desarrollo: genera historial de comportamiento realista (registros repartidos en el
 * tiempo, eventos, swipes, matches, mensajes, datecitos, compras y reportes) para ver el CRM con datos.
 */
#[Signature('datecito:fake-activity
    {--days=90 : Cuántos días de historial generar}
    {--activity=10 : Porcentaje de personas activas en un día típico}
    {--fresh : Borra antes eventos, swipes, conexiones, compras y reportes de las personas de prueba}')]
#[Description('Genera historial de comportamiento de prueba para el CRM (solo local)')]
class FakeActivity extends Command
{
    private const API_EVENTS = ['me.show' => 20, 'discover' => 30, 'swipes.store' => 60, 'connections.index' => 18, 'messages.index' => 14, 'messages.store' => 12, 'activity' => 8, 'datecitos.index' => 6, 'here.store' => 2, 'here.people' => 3, 'me.update' => 2, 'photos.store' => 1, 'me.filters' => 2];

    private const SCREENS = ['screen.discover' => 30, 'screen.chats' => 18, 'screen.chat' => 14, 'screen.profile' => 8, 'screen.datecitos' => 6, 'screen.here' => 3, 'screen.membership' => 4, 'screen.settings' => 3, 'screen.safety' => 2, 'paywall.view' => 3, 'paywall.dismiss' => 2];

    private const VERSIONS = ['1.0.0' => 15, '1.1.0' => 35, '1.2.0' => 50];

    private const PLACES = ['Café Avellaneda', 'Mercado Roma', 'Cineteca Nacional', 'Parque México', 'Terraza Catedral', 'Bar Felina', 'Museo Tamayo', 'Chapultepec'];

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->components->error('Este comando sólo funciona en local.');

            return self::FAILURE;
        }

        $days = max(7, (int) $this->option('days'));
        $activity = max(1, min(100, (int) $this->option('activity')));
        // Sólo las personas de prueba: nunca se tocan cuentas reales (verificación, fechas, matches).
        $users = User::query()->where('role', 'user')->where('email', 'like', '%@'.FakeUsers::DOMAIN)->get(['id', 'email', 'created_at', 'gender']);

        if ($users->count() < 20) {
            $this->components->error('Se necesitan al menos 20 personas. Ejecuta antes: php artisan datecito:fake-users 300');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->components->task('Borrando historial anterior', function () use ($users): void {
                $ids = $users->pluck('id');
                DB::table('activity_events')->whereIn('user_id', $ids)->orWhereNull('user_id')->delete();
                DB::table('swipes')->whereIn('user_id', $ids)->delete();
                DB::table('connections')->whereIn('user_one_id', $ids)->orWhereIn('user_two_id', $ids)->delete();
                DB::table('subscriptions')->whereIn('user_id', $ids)->delete();
                DB::table('purchases')->whereIn('user_id', $ids)->delete();
                DB::table('reports')->whereIn('reporter_id', $ids)->delete();
                DB::table('blocks')->whereIn('blocker_id', $ids)->delete();
            });
        }

        $start = CarbonImmutable::today()->subDays($days - 1);

        $this->components->task('Repartiendo registros en el tiempo', fn () => $this->spreadSignups($users, $start, $days));
        $users = User::query()->whereKey($users->pluck('id'))->get(['id', 'created_at', 'gender']);

        $events = [];
        $swipes = [];
        $connections = [];
        $this->components->task("Generando $days días de actividad", function () use ($users, $start, $days, $activity, &$events, &$swipes, &$connections): void {
            for ($d = 0; $d < $days; $d++) {
                $day = $start->addDays($d);
                $weekendBoost = in_array($day->dayOfWeekIso, [5, 6, 7], true) ? 1.35 : 1.0;
                $existing = $users->filter(fn (User $u) => $u->created_at->lessThanOrEqualTo($day->endOfDay()));
                $active = $existing->filter(fn (User $u) => random_int(1, 100) <= $activity * $weekendBoost * ($u->created_at->diffInDays($day) < 3 ? 3 : 1));

                foreach ($active as $user) {
                    $this->simulateDay($user, $day, $existing, $events, $swipes, $connections);
                }

                if (count($events) > 5000) {
                    $this->flush($events, $swipes, $connections);
                }
            }

            $this->flush($events, $swipes, $connections);
        });

        $this->components->task('Creando conversaciones, datecitos y Datecito Seguro', fn () => $this->conversations());
        $this->components->task('Creando membresías, compras y reportes', fn () => $this->commerceAndSafety($users, $start, $days));

        $this->components->info('Listo. Abre el CRM para ver el historial.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function spreadSignups(Collection $users, CarbonImmutable $start, int $days): void
    {
        foreach ($users as $user) {
            // Más registros hacia el final (crecimiento) y algunos antes del periodo.
            $offset = random_int(0, 100) < 8 ? -random_int(1, 120) : (int) floor($days * sqrt(mt_rand() / mt_getrandmax()));
            $createdAt = $start->addDays($offset)->setTime(random_int(8, 23), random_int(0, 59), random_int(0, 59));
            $platform = $this->weighted(['ios' => 45, 'android' => 55]);

            DB::table('users')->where('id', $user->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'email_verified_at' => random_int(1, 100) <= 88 ? $createdAt->addMinutes(random_int(1, 30)) : null,
                'signup_platform' => $platform,
                'last_platform' => $platform,
                'last_app_version' => $this->weighted(self::VERSIONS),
            ]);
        }
    }

    /**
     * @param  Collection<int, User>  $pool
     * @param  list<array<string, mixed>>  $events
     * @param  list<array<string, mixed>>  $swipes
     * @param  list<array<string, mixed>>  $connections
     */
    private function simulateDay(User $user, CarbonImmutable $day, Collection $pool, array &$events, array &$swipes, array &$connections): void
    {
        $platform = $this->weighted(['ios' => 45, 'android' => 55]);
        $version = $this->weighted(self::VERSIONS);
        $sessions = random_int(1, 3);

        for ($s = 0; $s < $sessions; $s++) {
            $at = $day->setTime($this->weighted([8 => 4, 10 => 5, 12 => 8, 14 => 7, 16 => 7, 18 => 10, 20 => 16, 21 => 18, 22 => 15, 23 => 10]), random_int(0, 59), random_int(0, 59));
            $actions = random_int(3, 18);

            $events[] = $this->event($user->id, 'session.start', ActivityEvent::CATEGORY_CLIENT, $at, $platform, $version);

            for ($i = 0; $i < $actions; $i++) {
                $at = $at->addSeconds(random_int(5, 90));
                $name = $this->weighted(random_int(0, 1) ? self::API_EVENTS : self::SCREENS);
                $category = str_starts_with($name, 'screen.') || str_starts_with($name, 'paywall.') ? ActivityEvent::CATEGORY_CLIENT : ActivityEvent::CATEGORY_API;
                $properties = ['method' => 'GET'];

                if ($name === 'swipes.store') {
                    $target = $pool->random();
                    if ($target->id === $user->id) {
                        continue;
                    }
                    $type = $this->weighted([SwipeType::Like->value => 42, SwipeType::Pass->value => 55, SwipeType::Super->value => 3]);
                    $swipes[] = ['user_id' => $user->id, 'target_id' => $target->id, 'type' => $type, 'created_at' => $at];
                    $properties = ['method' => 'POST', 'type' => $type];

                    if ($type !== SwipeType::Pass->value && random_int(1, 100) <= 22) {
                        [$one, $two] = Connection::orderedPair($user->id, $target->id);
                        $swipes[] = ['user_id' => $target->id, 'target_id' => $user->id, 'type' => SwipeType::Like->value, 'created_at' => $at->subMinutes(random_int(5, 600))];
                        $connections[] = ['id' => (string) Str::ulid(), 'user_one_id' => $one, 'user_two_id' => $two, 'source' => ConnectionSource::Discover->value, 'created_at' => $at, 'updated_at' => $at];
                        $events[] = $this->event($user->id, 'connection.created', ActivityEvent::CATEGORY_SYSTEM, $at, null, null, ['source' => 'discover']);
                        $events[] = $this->event($target->id, 'connection.created', ActivityEvent::CATEGORY_SYSTEM, $at, null, null, ['source' => 'discover']);
                    }
                } elseif (in_array($name, ['messages.store', 'me.update', 'photos.store', 'here.store'], true)) {
                    $properties = ['method' => 'POST'];
                }

                $status = $category === ActivityEvent::CATEGORY_API ? $this->weighted([200 => 93, 201 => 4, 422 => 2, 500 => 1]) : null;
                $events[] = $this->event($user->id, $name, $category, $at, $platform, $version, $properties, $status);
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $properties
     * @return array<string, mixed>
     */
    private function event(string $userId, string $name, string $category, CarbonImmutable $at, ?string $platform, ?string $version, ?array $properties = null, ?int $status = null): array
    {
        return [
            'user_id' => $userId,
            'name' => $name,
            'category' => $category,
            'properties' => $properties === null ? null : json_encode($properties),
            'platform' => $platform,
            'app_version' => $version,
            'ip_hash' => null,
            'status' => $status,
            'duration_ms' => $category === ActivityEvent::CATEGORY_API ? random_int(25, 600) : null,
            'occurred_at' => $at,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  list<array<string, mixed>>  $swipes
     * @param  list<array<string, mixed>>  $connections
     */
    private function flush(array &$events, array &$swipes, array &$connections): void
    {
        DB::transaction(function () use (&$events, &$swipes, &$connections): void {
            foreach (array_chunk($events, 1000) as $chunk) {
                DB::table('activity_events')->insert($chunk);
            }
            foreach (array_chunk($swipes, 1000) as $chunk) {
                DB::table('swipes')->insertOrIgnore($chunk);
            }
            foreach (array_chunk($connections, 500) as $chunk) {
                DB::table('connections')->insertOrIgnore($chunk);
            }
        });

        $events = [];
        $swipes = [];
        $connections = [];
    }

    private function conversations(): void
    {
        $lines = ['Hola, ¿cómo va tu semana?', 'Vi que te gusta el café, ¿algún lugar favorito?', 'Jaja sí, totalmente', '¿Te late un plan el fin?', 'Me encantaría ✨', 'Perfecto, yo llevo el buen humor', 'Ese lugar es buenísimo', '¿Domingo por la tarde?', 'Va, ahí nos vemos 🙌', 'Gracias por la recomendación'];

        $seed = fn ($q) => $q->where('email', 'like', '%@'.FakeUsers::DOMAIN);

        Connection::query()->whereHas('userOne', $seed)->whereHas('userTwo', $seed)->whereNull('last_message_at')->whereDoesntHave('messages')->inRandomOrder()->limit(400)->get()->each(function (Connection $connection) use ($lines): void {
            if (random_int(1, 100) > 65) {
                return;
            }

            $at = $connection->created_at->toImmutable()->addMinutes(random_int(10, 2000));
            $count = random_int(2, 12);
            for ($i = 0; $i < $count; $i++) {
                $sender = $i % 2 === 0 ? $connection->user_one_id : $connection->user_two_id;
                $message = $connection->messages()->make(['sender_id' => $sender, 'body' => Arr::random($lines)]);
                $message->forceFill(['created_at' => $at])->save();
                $at = $at->addMinutes(random_int(1, 400));
            }
            $connection->forceFill(['last_message_at' => $at])->save();

            if (random_int(1, 100) <= 40) {
                $status = $this->weighted([DatecitoStatus::Proposed->value => 30, DatecitoStatus::Confirmed->value => 55, DatecitoStatus::Cancelled->value => 15]);
                $scheduled = $at->addDays(random_int(1, 10));
                $datecito = $connection->datecitos()->make([
                    'proposer_id' => random_int(0, 1) ? $connection->user_one_id : $connection->user_two_id,
                    'kind' => Arr::random(config('datecito.catalog.plan_kinds')),
                    'place' => Arr::random(self::PLACES),
                    'zone' => Arr::random(['Roma Norte', 'Condesa', 'Coyoacán', 'Centro', 'Juárez', 'Polanco']),
                    'when_label' => Arr::random(['Sábado por la tarde', 'Domingo por la mañana', 'Viernes en la noche', 'Jueves después del trabajo']),
                    'scheduled_on' => $scheduled->toDateString(),
                ]);
                $datecito->forceFill([
                    'status' => $status,
                    'confirmed_at' => $status === DatecitoStatus::Confirmed->value ? $at->addHours(random_int(1, 48)) : null,
                    'cancelled_at' => $status === DatecitoStatus::Cancelled->value ? $at->addHours(random_int(1, 72)) : null,
                    'created_at' => $at,
                    'updated_at' => $at,
                ])->save();

                if ($status === DatecitoStatus::Confirmed->value && random_int(1, 100) <= 35) {
                    $userId = $datecito->proposer_id;
                    $contact = DB::table('trusted_contacts')->where('user_id', $userId)->value('id');
                    if ($contact === null) {
                        $contact = (string) Str::ulid();
                        DB::table('trusted_contacts')->insert(['id' => $contact, 'user_id' => $userId, 'name' => encrypt('Contacto demo'), 'phone' => encrypt('55 0000 0000'), 'created_at' => $at, 'updated_at' => $at]);
                    }
                    $sessionStatus = $scheduled->isPast() ? $this->weighted(['ended' => 92, 'help' => 3, 'overdue' => 5]) : 'active';
                    DB::table('safe_sessions')->insert([
                        'id' => (string) Str::ulid(), 'user_id' => $userId, 'datecito_id' => $datecito->id, 'contact_ids' => json_encode([$contact]),
                        'check_in_at' => $scheduled->setTime(22, 0), 'share_location' => random_int(0, 1), 'status' => $sessionStatus,
                        'help_requested_at' => $sessionStatus === 'help' ? $scheduled->setTime(21, 30) : null,
                        'ended_at' => $sessionStatus === 'ended' ? $scheduled->setTime(23, 0) : null,
                        'created_at' => $scheduled->setTime(19, 0), 'updated_at' => $scheduled->setTime(19, 0),
                    ]);
                }
            }
        });
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function commerceAndSafety(Collection $users, CarbonImmutable $start, int $days): void
    {
        $pricing = config('datecito.pricing');
        $reasons = config('datecito.catalog.report_reasons');

        foreach ($users as $user) {
            $roll = random_int(1, 100);
            if ($roll <= 9) {
                $plan = random_int(1, 100) <= 70 ? SubscriptionPlan::Monthly : SubscriptionPlan::Yearly;
                $boughtAt = CarbonImmutable::parse(max($user->created_at->toImmutable(), $start)->toDateTimeString())->addDays(random_int(0, max(1, $days - 1)));
                $expires = $plan === SubscriptionPlan::Yearly ? $boughtAt->addYear() : $boughtAt->addMonth();
                DB::table('subscriptions')->insertOrIgnore([
                    'id' => (string) Str::ulid(), 'user_id' => $user->id, 'plan' => $plan->value,
                    'store' => $this->weighted([PurchaseStore::AppStore->value => 45, PurchaseStore::GooglePlay->value => 55]),
                    'store_reference' => 'fake-'.Str::random(16), 'status' => $expires->isPast() ? SubscriptionStatus::Expired->value : SubscriptionStatus::Active->value,
                    'starts_at' => $boughtAt, 'expires_at' => $expires, 'created_at' => $boughtAt, 'updated_at' => $boughtAt,
                ]);
                DB::table('activity_events')->insert($this->event($user->id, 'membership.purchase', ActivityEvent::CATEGORY_API, $boughtAt, $this->weighted(['ios' => 45, 'android' => 55]), '1.2.0', ['method' => 'POST', 'product_id' => $pricing[$plan->value]['product_id']], 201));
            }
            if ($roll >= 85 && $roll <= 92) {
                $boughtAt = CarbonImmutable::parse(max($user->created_at->toImmutable(), $start)->toDateTimeString())->addDays(random_int(0, max(1, $days - 1)));
                DB::table('purchases')->insertOrIgnore([
                    'id' => (string) Str::ulid(), 'user_id' => $user->id, 'product_id' => $pricing['super_like_pack']['product_id'],
                    'store' => $this->weighted([PurchaseStore::AppStore->value => 45, PurchaseStore::GooglePlay->value => 55]),
                    'store_reference' => 'fake-'.Str::random(16), 'quantity' => 10, 'created_at' => $boughtAt,
                ]);
            }
            if ($roll >= 96) {
                $reported = $users->random();
                if ($reported->id !== $user->id) {
                    $at = CarbonImmutable::parse(max($user->created_at->toImmutable(), $start)->toDateTimeString())->addDays(random_int(0, max(1, $days - 1)));
                    $status = $this->weighted([ReportStatus::Open->value => 35, ReportStatus::Reviewing->value => 10, ReportStatus::Resolved->value => 40, ReportStatus::Dismissed->value => 15]);
                    DB::table('reports')->insert([
                        'id' => (string) Str::ulid(), 'reporter_id' => $user->id, 'reported_id' => $reported->id, 'reason' => Arr::random($reasons),
                        'details' => encrypt(Arr::random(['Me escribió cosas incómodas.', 'Las fotos no parecen reales.', 'Insistió en vernos fuera de la app.', null])),
                        'status' => $status, 'created_at' => $at, 'updated_at' => $at,
                    ]);
                    DB::table('activity_events')->insert($this->event($user->id, 'reports.store', ActivityEvent::CATEGORY_API, $at, 'android', '1.2.0', ['method' => 'POST'], 201));
                }
            }
        }

        // Última actividad coherente con los eventos generados.
        DB::statement('UPDATE users u SET last_seen_at = (SELECT MAX(occurred_at) FROM activity_events e WHERE e.user_id = u.id) WHERE role = ?', ['user']);
    }

    /**
     * @template T of array-key
     *
     * @param  array<T, int|float>  $weights
     * @return T
     */
    private function weighted(array $weights): int|string
    {
        $roll = mt_rand() / mt_getrandmax() * array_sum($weights);

        foreach ($weights as $value => $weight) {
            if (($roll -= $weight) <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }
}
