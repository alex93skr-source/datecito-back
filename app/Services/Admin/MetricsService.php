<?php

namespace App\Services\Admin;

use App\Enums\DatecitoStatus;
use App\Enums\ReportStatus;
use App\Enums\SafeSessionStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\SwipeType;
use App\Enums\VerificationStatus;
use App\Models\ActivityEvent;
use App\Models\HereSession;
use App\Models\Report;
use App\Models\SafeSession;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Métricas del CRM: KPIs, series de tiempo, embudo, retención y distribuciones.
 *
 * Todo se calcula sobre MySQL con índices por fecha; los resultados se cachean
 * unos segundos para que varios miembros del equipo no repitan las mismas consultas.
 */
class MetricsService
{
    /**
     * Métricas disponibles para series de tiempo (clave => etiqueta).
     */
    public const SERIES = [
        'signups' => 'Registros',
        'active_users' => 'Personas activas',
        'deletions' => 'Cuentas eliminadas',
        'swipes' => 'Swipes',
        'likes' => 'Likes',
        'super_likes' => 'Super likes',
        'matches' => 'Matches',
        'messages' => 'Mensajes',
        'datecitos' => 'Datecitos propuestos',
        'datecitos_confirmed' => 'Datecitos confirmados',
        'here_sessions' => 'Sesiones Aquí y ahora',
        'safe_sessions' => 'Sesiones Datecito Seguro',
        'reports' => 'Reportes',
        'verifications' => 'Solicitudes de verificación',
        'subscriptions' => 'Suscripciones nuevas',
        'purchases' => 'Paquetes de Super likes',
        'revenue' => 'Ingresos (USD)',
        'events' => 'Eventos',
        'errors' => 'Errores de API',
    ];

    /**
     * @return array<string, mixed>
     */
    public function overview(Period $period): array
    {
        return $this->remember('overview', $period, function () use ($period): array {
            $previous = $period->previous();
            $now = CarbonImmutable::now();

            $compare = fn (callable $count) => $this->compare($count($period), $count($previous));

            return [
                'period' => $period->toArray(),
                'totals' => [
                    'users' => User::query()->count(),
                    'users_active_status' => User::query()->where('status', 'active')->count(),
                    'email_verified' => User::query()->whereNotNull('email_verified_at')->count(),
                    'profile_verified' => User::query()->whereNotNull('verified_at')->count(),
                    'members' => $this->activeMembers(),
                    'connections_active' => DB::table('connections')->whereNull('ended_at')->count(),
                    'reports_open' => Report::query()->whereIn('status', [ReportStatus::Open, ReportStatus::Reviewing])->count(),
                    'verifications_pending' => VerificationRequest::query()->where('status', VerificationStatus::Pending)->count(),
                    'here_live' => HereSession::query()->live()->count(),
                    'safe_sessions_active' => SafeSession::query()->whereIn('status', [SafeSessionStatus::Active, SafeSessionStatus::Help, SafeSessionStatus::Overdue])->count(),
                    'safe_sessions_help' => SafeSession::query()->whereIn('status', [SafeSessionStatus::Help, SafeSessionStatus::Overdue])->count(),
                ],
                'activity' => [
                    'dau' => $this->activeUsersBetween($now->startOfDay(), $now->addDay()),
                    'wau' => $this->activeUsersBetween($now->subDays(7), $now->addMinute()),
                    'mau' => $this->activeUsersBetween($now->subDays(30), $now->addMinute()),
                    'online_15m' => $this->activeUsersBetween($now->subMinutes(15), $now->addMinute()),
                ],
                'kpis' => [
                    'signups' => $compare(fn (Period $p) => $this->countBetween('users', 'created_at', $p)),
                    'email_verified' => $compare(fn (Period $p) => $this->countBetween('users', 'email_verified_at', $p)),
                    'active_users' => $compare(fn (Period $p) => $this->activeUsersBetween($p->from, $p->to)),
                    'deletions' => $compare(fn (Period $p) => ActivityEvent::query()->where('name', 'account.deleted')->between($p->from, $p->to)->count()),
                    'swipes' => $compare(fn (Period $p) => $this->countBetween('swipes', 'created_at', $p)),
                    'likes' => $compare(fn (Period $p) => $this->countBetween('swipes', 'created_at', $p, fn (Builder $q) => $q->whereIn('type', [SwipeType::Like->value, SwipeType::Super->value]))),
                    'matches' => $compare(fn (Period $p) => $this->countBetween('connections', 'created_at', $p)),
                    'messages' => $compare(fn (Period $p) => $this->countBetween('messages', 'created_at', $p)),
                    'datecitos' => $compare(fn (Period $p) => $this->countBetween('datecitos', 'created_at', $p)),
                    'datecitos_confirmed' => $compare(fn (Period $p) => $this->countBetween('datecitos', 'confirmed_at', $p)),
                    'reports' => $compare(fn (Period $p) => $this->countBetween('reports', 'created_at', $p)),
                    'subscriptions' => $compare(fn (Period $p) => $this->countBetween('subscriptions', 'created_at', $p)),
                    'purchases' => $compare(fn (Period $p) => $this->countBetween('purchases', 'created_at', $p)),
                    'revenue' => $compare(fn (Period $p) => $this->revenueBetween($p)),
                    'events' => $compare(fn (Period $p) => $this->countBetween('activity_events', 'occurred_at', $p)),
                    'errors' => $compare(fn (Period $p) => $this->countBetween('activity_events', 'occurred_at', $p, fn (Builder $q) => $q->where('status', '>=', 500))),
                ],
                'rates' => $this->rates($period),
            ];
        });
    }

    /**
     * @return array{period: array<string, mixed>, metric: string, label: string, series: list<array{date: string, value: int|float}>}
     */
    public function timeseries(Period $period, string $metric, ?string $eventName = null): array
    {
        $key = $metric.':'.($eventName ?? '');

        return $this->remember('series:'.$key, $period, function () use ($period, $metric, $eventName): array {
            $bucket = $period->bucket($this->seriesColumn($metric));
            $query = $this->seriesQuery($metric, $eventName, $period);

            $rows = match ($metric) {
                'active_users' => $query->selectRaw('COUNT(DISTINCT user_id) as value')->addSelect(DB::raw("$bucket as bucket")),
                'revenue' => null,
                default => $query->selectRaw('COUNT(*) as value')->addSelect(DB::raw("$bucket as bucket")),
            };

            $series = $metric === 'revenue'
                ? $this->revenueSeries($period)
                : $period->series($rows->groupBy('bucket')->orderBy('bucket')->get());

            return [
                'period' => $period->toArray(),
                'metric' => $metric,
                'label' => $metric === 'events' && $eventName !== null ? $eventName : (self::SERIES[$metric] ?? $metric),
                'series' => $series,
            ];
        });
    }

    /**
     * Embudo de activación para la cohorte de personas registradas en el periodo.
     *
     * @return array{period: array<string, mixed>, steps: list<array{key: string, label: string, count: int, pct_of_total: float, pct_of_previous: float}>}
     */
    public function funnel(Period $period): array
    {
        return $this->remember('funnel', $period, function () use ($period): array {
            $cohort = fn () => DB::table('users')->where('created_at', '>=', $period->from)->where('created_at', '<', $period->to);
            $exists = fn (string $table, string $column) => fn (Builder $q) => $q->whereExists(
                fn (Builder $sub) => $sub->selectRaw('1')->from($table)->whereColumn("$table.$column", 'users.id'),
            );

            $involved = fn (Builder $c) => $c->whereColumn('connections.user_one_id', 'users.id')->orWhereColumn('connections.user_two_id', 'users.id');
            $datecito = fn (?string $status) => fn (Builder $q) => $q->whereExists(
                fn (Builder $sub) => $sub->selectRaw('1')->from('datecitos')
                    ->join('connections', 'connections.id', '=', 'datecitos.connection_id')
                    ->when($status !== null, fn (Builder $d) => $d->where('datecitos.status', $status))
                    ->where($involved),
            );

            // Embudo acumulativo: cada paso cuenta sólo a quienes también pasaron los anteriores.
            $steps = [
                ['registered', 'Se registró', fn (Builder $q) => $q],
                ['email_verified', 'Verificó su correo', fn (Builder $q) => $q->whereNotNull('email_verified_at')],
                ['profile_basic', 'Completó lo básico del perfil', fn (Builder $q) => $q->whereNotNull('name')->whereNotNull('birthdate')->whereNotNull('bio')],
                ['has_photo', 'Subió una foto', $exists('photos', 'user_id')],
                ['first_swipe', 'Hizo su primer swipe', $exists('swipes', 'user_id')],
                ['first_match', 'Tuvo su primer match', fn (Builder $q) => $q->whereExists(fn (Builder $sub) => $sub->selectRaw('1')->from('connections')->where($involved))],
                ['first_message', 'Envió su primer mensaje', $exists('messages', 'sender_id')],
                ['first_datecito', 'Tuvo un datecito (propuesto o recibido)', $datecito(null)],
                ['datecito_confirmed', 'Confirmó un datecito', $datecito(DatecitoStatus::Confirmed->value)],
            ];

            $result = [];
            $total = null;
            $previous = null;
            $applied = [];

            foreach ($steps as [$key, $label, $filter]) {
                $applied[] = $filter;
                $query = $cohort();
                foreach ($applied as $apply) {
                    $apply($query);
                }
                $count = (int) $query->count();
                $total ??= $count;
                $result[] = [
                    'key' => $key,
                    'label' => $label,
                    'count' => $count,
                    'pct_of_total' => $this->pct($count, $total),
                    'pct_of_previous' => $this->pct($count, $previous ?? $count),
                ];
                $previous = $count;
            }

            return [
                'period' => $period->toArray(),
                'steps' => $result,
                // La verificación con selfie no es un paso obligatorio del camino, se reporta aparte.
                'selfie_verified' => (int) $cohort()->whereNotNull('verified_at')->count(),
            ];
        });
    }

    /**
     * Retención semanal por cohorte de registro (porcentaje de la cohorte con actividad en la semana N).
     *
     * @return array{weeks: int, cohorts: list<array{cohort: string, size: int, retention: list<float|null>}>}
     */
    public function retention(int $weeks = 8): array
    {
        $weeks = max(2, min(16, $weeks));
        $period = new Period(CarbonImmutable::now()->startOfWeek()->subWeeks($weeks - 1), CarbonImmutable::tomorrow(), 'week');

        return $this->remember('retention:'.$weeks, $period, function () use ($period, $weeks): array {
            $cohortExpr = 'DATE(DATE_SUB(users.created_at, INTERVAL WEEKDAY(users.created_at) DAY))';

            $sizes = DB::table('users')
                ->where('created_at', '>=', $period->from)
                ->selectRaw("$cohortExpr as cohort, COUNT(*) as size")
                ->groupBy('cohort')
                ->pluck('size', 'cohort');

            $retained = DB::table('users')
                ->join('activity_events', 'activity_events.user_id', '=', 'users.id')
                ->where('users.created_at', '>=', $period->from)
                ->whereRaw("activity_events.occurred_at >= $cohortExpr")
                ->selectRaw("$cohortExpr as cohort, FLOOR(DATEDIFF(activity_events.occurred_at, $cohortExpr) / 7) as wk, COUNT(DISTINCT users.id) as users")
                ->groupBy('cohort', 'wk')
                ->get()
                ->groupBy('cohort');

            $today = CarbonImmutable::now();
            $cohorts = [];

            foreach ($period->buckets() as $cohort) {
                $size = (int) ($sizes[$cohort] ?? 0);
                $byWeek = ($retained[$cohort] ?? collect())->keyBy('wk');
                $start = CarbonImmutable::parse($cohort);
                $row = [];

                for ($week = 0; $week < $weeks; $week++) {
                    $weekStart = $start->addWeeks($week);
                    $row[] = $weekStart->greaterThan($today) || $size === 0 ? null : $this->pct((int) ($byWeek[$week]->users ?? 0), $size);
                }

                $cohorts[] = ['cohort' => $cohort, 'size' => $size, 'retention' => $row];
            }

            return ['weeks' => $weeks, 'cohorts' => $cohorts];
        });
    }

    /**
     * Distribuciones: quiénes son las personas y qué hacen en el periodo.
     *
     * @return array<string, mixed>
     */
    public function breakdowns(Period $period): array
    {
        return $this->remember('breakdowns', $period, function () use ($period): array {
            $distribution = fn (Builder $query, string $column, int $limit = 12) => $query
                ->selectRaw("COALESCE($column, 'Sin dato') as label, COUNT(*) as value")
                ->groupBy('label')
                ->orderByDesc('value')
                ->limit($limit)
                ->get()
                ->map(fn (object $row) => ['label' => (string) $row->label, 'value' => (int) $row->value])
                ->all();

            $inPeriod = fn (string $table, string $column = 'created_at') => DB::table($table)->where($column, '>=', $period->from)->where($column, '<', $period->to);

            $ageExpr = "CASE
                WHEN birthdate IS NULL THEN 'Sin dato'
                WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 25 THEN '18-24'
                WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 35 THEN '25-34'
                WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 45 THEN '35-44'
                ELSE '45+' END";

            $members = Subscription::query()->where('status', SubscriptionStatus::Active)->where('expires_at', '>', now())
                ->selectRaw('plan as label, COUNT(DISTINCT user_id) as value')->groupBy('plan')->get()
                ->map(fn (object $row) => ['label' => (string) $row->label, 'value' => (int) $row->value])->all();
            $members[] = ['label' => 'Sin membresía', 'value' => max(0, User::query()->count() - array_sum(array_column($members, 'value')))];

            return [
                'period' => $period->toArray(),
                'users' => [
                    'gender' => $distribution(DB::table('users'), 'gender'),
                    'age' => $distribution(DB::table('users'), $ageExpr, 6),
                    'zone' => $distribution(DB::table('users'), 'zone', 10),
                    'intent' => $distribution(DB::table('users'), 'intent'),
                    'relationship_type' => $distribution(DB::table('users'), 'relationship_type'),
                    'status' => $distribution(DB::table('users'), 'status'),
                    'verification' => $distribution(DB::table('users'), 'verification_status'),
                    'platform' => $distribution(DB::table('users'), 'last_platform'),
                    'membership' => $members,
                    'photos' => DB::table('users')
                        ->leftJoin('photos', 'photos.user_id', '=', 'users.id')
                        ->selectRaw('LEAST(COUNT(photos.id), 5) as n')
                        ->groupBy('users.id')
                        ->get()
                        ->groupBy('n')
                        ->map(fn (Collection $rows, int $n) => ['label' => $n >= 5 ? '5+' : (string) $n, 'value' => $rows->count()])
                        ->sortKeys()
                        ->values()
                        ->all(),
                ],
                'behavior' => [
                    'swipe_types' => $distribution($inPeriod('swipes'), 'type'),
                    'connection_sources' => $distribution($inPeriod('connections'), 'source'),
                    'datecito_kinds' => $distribution($inPeriod('datecitos'), 'kind'),
                    'datecito_status' => $distribution($inPeriod('datecitos'), 'status'),
                    'datecito_zones' => $distribution($inPeriod('datecitos'), 'zone', 10),
                    'report_reasons' => $distribution($inPeriod('reports'), 'reason'),
                    'report_status' => $distribution($inPeriod('reports'), 'status'),
                    'event_platforms' => $distribution($inPeriod('activity_events', 'occurred_at'), 'platform'),
                    'app_versions' => $distribution($inPeriod('activity_events', 'occurred_at'), 'app_version', 8),
                    'top_events' => $this->topEvents($period, 15),
                    'heatmap' => $this->heatmap($period),
                ],
            ];
        });
    }

    /**
     * Qué está pasando ahora mismo (últimos minutos).
     *
     * @return array<string, mixed>
     */
    public function live(): array
    {
        $now = CarbonImmutable::now();
        $today = new Period($now->startOfDay(), $now->addDay());

        return [
            'at' => $now->toIso8601String(),
            'online_15m' => $this->activeUsersBetween($now->subMinutes(15), $now->addMinute()),
            'events_per_minute' => round($this->countBetween('activity_events', 'occurred_at', new Period($now->subMinutes(5), $now->addMinute())) / 5, 1),
            'errors_15m' => ActivityEvent::query()->where('status', '>=', 500)->between($now->subMinutes(15), $now->addMinute())->count(),
            'here_live' => HereSession::query()->live()->count(),
            'safe_sessions' => [
                'active' => SafeSession::query()->where('status', SafeSessionStatus::Active)->count(),
                'help' => SafeSession::query()->where('status', SafeSessionStatus::Help)->count(),
                'overdue' => SafeSession::query()->where('status', SafeSessionStatus::Overdue)->count(),
            ],
            'reports_open' => Report::query()->whereIn('status', [ReportStatus::Open, ReportStatus::Reviewing])->count(),
            'verifications_pending' => VerificationRequest::query()->where('status', VerificationStatus::Pending)->count(),
            'today' => [
                'signups' => $this->countBetween('users', 'created_at', $today),
                'swipes' => $this->countBetween('swipes', 'created_at', $today),
                'matches' => $this->countBetween('connections', 'created_at', $today),
                'messages' => $this->countBetween('messages', 'created_at', $today),
                'datecitos' => $this->countBetween('datecitos', 'created_at', $today),
                'revenue' => $this->revenueBetween($today),
            ],
            'recent_events' => ActivityEvent::query()
                ->with('user:id,name,email')
                ->latest('occurred_at')
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (ActivityEvent $event) => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'category' => $event->category,
                    'platform' => $event->platform,
                    'status' => $event->status,
                    'user' => $event->user === null ? null : ['id' => $event->user->id, 'name' => $event->user->name],
                    'occurred_at' => $event->occurred_at->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * Eventos más frecuentes en el periodo, con personas distintas.
     *
     * @return list<array{name: string, category: string, count: int, users: int}>
     */
    public function topEvents(Period $period, int $limit = 50, ?string $category = null): array
    {
        return DB::table('activity_events')
            ->where('occurred_at', '>=', $period->from)->where('occurred_at', '<', $period->to)
            ->when($category !== null, fn (Builder $q) => $q->where('category', $category))
            ->selectRaw('name, category, COUNT(*) as count, COUNT(DISTINCT user_id) as users')
            ->groupBy('name', 'category')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn (object $row) => ['name' => $row->name, 'category' => $row->category, 'count' => (int) $row->count, 'users' => (int) $row->users])
            ->all();
    }

    /**
     * Actividad por día de la semana (0 = lunes) y hora.
     *
     * @return list<array{dow: int, hour: int, value: int}>
     */
    public function heatmap(Period $period): array
    {
        return DB::table('activity_events')
            ->where('occurred_at', '>=', $period->from)->where('occurred_at', '<', $period->to)
            ->selectRaw('WEEKDAY(occurred_at) as dow, HOUR(occurred_at) as hour, COUNT(*) as value')
            ->groupBy('dow', 'hour')
            ->get()
            ->map(fn (object $row) => ['dow' => (int) $row->dow, 'hour' => (int) $row->hour, 'value' => (int) $row->value])
            ->all();
    }

    /**
     * Resumen de monetización.
     *
     * @return array<string, mixed>
     */
    public function billing(Period $period): array
    {
        return $this->remember('billing', $period, function () use ($period): array {
            $pricing = config('datecito.pricing');
            $previous = $period->previous();
            $byPlan = Subscription::query()->where('status', SubscriptionStatus::Active)->where('expires_at', '>', now())
                ->selectRaw('plan, COUNT(*) as n')->groupBy('plan')->pluck('n', 'plan');

            $monthly = (int) ($byPlan['monthly'] ?? 0);
            $yearly = (int) ($byPlan['yearly'] ?? 0);

            return [
                'period' => $period->toArray(),
                'currency' => $pricing['currency'],
                'pricing' => $pricing,
                'members' => ['total' => $monthly + $yearly, 'monthly' => $monthly, 'yearly' => $yearly],
                'mrr' => round($monthly * $pricing['monthly']['price'] + $yearly * $pricing['yearly']['price'] / 12, 2),
                'revenue' => $this->compare($this->revenueBetween($period), $this->revenueBetween($previous)),
                'subscriptions_new' => $this->compare($this->countBetween('subscriptions', 'created_at', $period), $this->countBetween('subscriptions', 'created_at', $previous)),
                'purchases_new' => $this->compare($this->countBetween('purchases', 'created_at', $period), $this->countBetween('purchases', 'created_at', $previous)),
                'expired' => $this->countBetween('subscriptions', 'expires_at', $period, fn (Builder $q) => $q->where('status', '!=', SubscriptionStatus::Cancelled->value)),
                'cancelled' => $this->countBetween('subscriptions', 'updated_at', $period, fn (Builder $q) => $q->where('status', SubscriptionStatus::Cancelled->value)),
                'by_store' => DB::table('subscriptions')->where('created_at', '>=', $period->from)->where('created_at', '<', $period->to)
                    ->selectRaw('store as label, COUNT(*) as value')->groupBy('store')->get()
                    ->map(fn (object $row) => ['label' => (string) $row->label, 'value' => (int) $row->value])->all(),
                'by_plan' => DB::table('subscriptions')->where('created_at', '>=', $period->from)->where('created_at', '<', $period->to)
                    ->selectRaw('plan as label, COUNT(*) as value')->groupBy('plan')->get()
                    ->map(fn (object $row) => ['label' => (string) $row->label, 'value' => (int) $row->value])->all(),
                'revenue_series' => $this->revenueSeries($period),
                'conversion' => [
                    'users' => User::query()->count(),
                    'ever_paid' => DB::table('users')->where(fn (Builder $q) => $q
                        ->whereExists(fn (Builder $s) => $s->selectRaw('1')->from('subscriptions')->whereColumn('subscriptions.user_id', 'users.id')->where('store_reference', 'not like', 'crm:%'))
                        ->orWhereExists(fn (Builder $s) => $s->selectRaw('1')->from('purchases')->whereColumn('purchases.user_id', 'users.id')))->count(),
                ],
                'launch_free' => (bool) config('datecito.launch_free'),
            ];
        });
    }

    /**
     * @return array<string, float>
     */
    private function rates(Period $period): array
    {
        $signups = $this->countBetween('users', 'created_at', $period);
        $likes = $this->countBetween('swipes', 'created_at', $period, fn (Builder $q) => $q->whereIn('type', [SwipeType::Like->value, SwipeType::Super->value]));
        $swipes = $this->countBetween('swipes', 'created_at', $period);
        $matches = $this->countBetween('connections', 'created_at', $period);
        $datecitos = $this->countBetween('datecitos', 'created_at', $period);
        $confirmed = $this->countBetween('datecitos', 'confirmed_at', $period);
        $withMessages = DB::table('connections')->where('created_at', '>=', $period->from)->where('created_at', '<', $period->to)->whereNotNull('last_message_at')->count();

        return [
            'email_verification' => $this->pct(DB::table('users')->where('created_at', '>=', $period->from)->where('created_at', '<', $period->to)->whereNotNull('email_verified_at')->count(), $signups),
            'like_rate' => $this->pct($likes, $swipes),
            'match_per_like' => $this->pct($matches * 2, $likes),
            'conversation_rate' => $this->pct($withMessages, $matches),
            'datecito_per_match' => $this->pct($datecitos, $matches),
            'datecito_confirmation' => $this->pct($confirmed, $datecitos),
        ];
    }

    private function seriesColumn(string $metric): string
    {
        return match ($metric) {
            'datecitos_confirmed' => 'confirmed_at',
            'active_users', 'events', 'errors', 'deletions' => 'occurred_at',
            default => 'created_at',
        };
    }

    private function seriesQuery(string $metric, ?string $eventName, Period $period): Builder
    {
        $column = $this->seriesColumn($metric);
        $table = match ($metric) {
            'signups' => 'users',
            'active_users', 'events', 'errors', 'deletions' => 'activity_events',
            'swipes', 'likes', 'super_likes' => 'swipes',
            'matches' => 'connections',
            'messages' => 'messages',
            'datecitos', 'datecitos_confirmed' => 'datecitos',
            'here_sessions' => 'here_sessions',
            'safe_sessions' => 'safe_sessions',
            'reports' => 'reports',
            'verifications' => 'verification_requests',
            'subscriptions' => 'subscriptions',
            'purchases' => 'purchases',
            default => 'activity_events',
        };

        $query = DB::table($table)->where($column, '>=', $period->from)->where($column, '<', $period->to);

        return match ($metric) {
            'likes' => $query->whereIn('type', [SwipeType::Like->value, SwipeType::Super->value]),
            'super_likes' => $query->where('type', SwipeType::Super->value),
            'errors' => $query->where('status', '>=', 500),
            'deletions' => $query->where('name', 'account.deleted'),
            'events' => $query->when($eventName !== null, fn (Builder $q) => $q->where('name', $eventName)),
            default => $query,
        };
    }

    /**
     * @return list<array{date: string, value: float|int}>
     */
    private function revenueSeries(Period $period): array
    {
        $pricing = config('datecito.pricing');
        $bucket = $period->bucket('created_at');
        $planPrice = sprintf("CASE plan WHEN 'yearly' THEN %F ELSE %F END", $pricing['yearly']['price'], $pricing['monthly']['price']);

        $subs = DB::table('subscriptions')
            ->where('created_at', '>=', $period->from)->where('created_at', '<', $period->to)
            ->where('store_reference', 'not like', 'crm:%')
            ->selectRaw("$bucket as bucket, SUM($planPrice) as value")
            ->groupBy('bucket')
            ->get();
        $packs = DB::table('purchases')
            ->where('created_at', '>=', $period->from)->where('created_at', '<', $period->to)
            ->selectRaw("$bucket as bucket, COUNT(*) * {$pricing['super_like_pack']['price']} as value")
            ->groupBy('bucket')
            ->get();

        $merged = collect([...$subs, ...$packs])
            ->groupBy(fn (object $row) => substr((string) $row->bucket, 0, 10))
            ->map(fn (Collection $rows, string $bucket) => (object) ['bucket' => $bucket, 'value' => $rows->sum('value')]);

        return $period->series($merged, float: true);
    }

    private function revenueBetween(Period $period): float
    {
        return round(array_sum(array_column($this->revenueSeries($period), 'value')), 2);
    }

    private function countBetween(string $table, string $column, Period $period, ?callable $extra = null): int
    {
        $query = DB::table($table)->where($column, '>=', $period->from)->where($column, '<', $period->to);

        if ($extra !== null) {
            $extra($query);
        }

        return (int) $query->count();
    }

    private function activeUsersBetween(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return (int) DB::table('activity_events')
            ->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');
    }

    private function activeMembers(): int
    {
        return (int) Subscription::query()->where('status', SubscriptionStatus::Active)->where('expires_at', '>', now())->distinct()->count('user_id');
    }

    /**
     * @return array{value: int|float, previous: int|float, change_pct: float|null}
     */
    private function compare(int|float $current, int|float $previous): array
    {
        return [
            'value' => $current,
            'previous' => $previous,
            'change_pct' => $previous == 0 ? ($current > 0 ? null : 0.0) : round(($current - $previous) / $previous * 100, 1),
        ];
    }

    private function pct(int|float $part, int|float $total): float
    {
        return $total == 0 ? 0.0 : round($part / $total * 100, 1);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function remember(string $key, Period $period, callable $callback): mixed
    {
        $seconds = (int) config('datecito.crm.cache_seconds');

        if ($seconds <= 0) {
            return $callback();
        }

        return Cache::remember('crm:'.$key.':'.$period->from->timestamp.':'.$period->to->timestamp.':'.$period->interval, $seconds, $callback);
    }
}
