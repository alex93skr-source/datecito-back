<?php

namespace App\Services;

use App\Jobs\RecordActivityEvent;
use App\Models\ActivityEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra eventos de comportamiento para el CRM.
 *
 * Todo pasa por la cola para no frenar la respuesta de la API. Sólo se guardan
 * propiedades de una lista blanca por ruta: jamás el cuerpo de un mensaje,
 * contraseñas, códigos ni coordenadas.
 */
class ActivityRecorder
{
    public const PLATFORMS = ['ios', 'android', 'web'];

    /**
     * Rutas que no aportan nada al análisis de comportamiento.
     */
    private const IGNORED_ROUTES = ['api.v1.catalog', 'api.v1.events.store'];

    /**
     * Propiedades que sí se guardan, por ruta.
     *
     * @var array<string, list<string>>
     */
    private const PROPERTIES = [
        'api.v1.swipes.store' => ['type'],
        'api.v1.datecitos.store' => ['kind', 'zone'],
        'api.v1.reports.store' => ['reason'],
        'api.v1.membership.purchase' => ['product_id', 'store'],
        'api.v1.here.store' => ['verified_only', 'minutes'],
    ];

    /**
     * Evento generado por una petición a la API, ya respondida.
     */
    public function recordRequest(Request $request, Response $response, float $startedAt): void
    {
        $route = $request->route()?->getName();

        if ($route === null || ! str_starts_with($route, 'api.v1.') || str_starts_with($route, 'api.v1.admin.') || in_array($route, self::IGNORED_ROUTES, true)) {
            return;
        }

        $properties = array_filter(
            $request->only(self::PROPERTIES[$route] ?? []),
            fn ($value) => is_scalar($value),
        );
        $properties['method'] = $request->method();

        if ($response->getStatusCode() >= 400) {
            $properties['error'] = $response->getStatusCode();
        }

        $this->queue([
            'user_id' => $request->user()?->id,
            'name' => Str::after($route, 'api.v1.'),
            'category' => ActivityEvent::CATEGORY_API,
            'properties' => $properties,
            'platform' => $this->platform($request),
            'app_version' => $this->appVersion($request),
            'ip_hash' => $this->ipHash($request),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'occurred_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Eventos enviados por la app (pantallas vistas, acciones de interfaz).
     *
     * @param  list<array{name: string, properties?: array<string, mixed>|null, occurred_at?: string|null}>  $events
     */
    public function recordClientEvents(User $user, Request $request, array $events): void
    {
        foreach ($events as $event) {
            $this->queue([
                'user_id' => $user->id,
                'name' => $event['name'],
                'category' => ActivityEvent::CATEGORY_CLIENT,
                'properties' => $event['properties'] ?? null,
                'platform' => $this->platform($request),
                'app_version' => $this->appVersion($request),
                'ip_hash' => $this->ipHash($request),
                'status' => null,
                'duration_ms' => null,
                'occurred_at' => isset($event['occurred_at']) ? now()->parse($event['occurred_at'])->toDateTimeString() : now()->toDateTimeString(),
            ]);
        }
    }

    /**
     * Evento generado por el servidor (por ejemplo, cuenta eliminada o match creado).
     *
     * @param  array<string, mixed>  $properties
     */
    public function recordSystem(?User $user, string $name, array $properties = []): void
    {
        $this->queue([
            'user_id' => $user?->id,
            'name' => $name,
            'category' => ActivityEvent::CATEGORY_SYSTEM,
            'properties' => $properties ?: null,
            'platform' => null,
            'app_version' => null,
            'ip_hash' => null,
            'status' => null,
            'duration_ms' => null,
            'occurred_at' => now()->toDateTimeString(),
        ]);
    }

    public function platform(Request $request): ?string
    {
        $platform = Str::lower((string) $request->header('X-Platform'));

        return in_array($platform, self::PLATFORMS, true) ? $platform : null;
    }

    public function appVersion(Request $request): ?string
    {
        $version = trim((string) $request->header('X-App-Version'));

        return $version !== '' && preg_match('/^[\w.\-+]{1,20}$/', $version) === 1 ? $version : null;
    }

    public function ipHash(Request $request): ?string
    {
        $ip = $request->ip();

        return $ip === null ? null : hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function queue(array $attributes): void
    {
        RecordActivityEvent::dispatch($attributes);
    }
}
