<?php

namespace App\Http\Middleware;

use App\Services\ActivityRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra cada petición a la API como evento de comportamiento (después de responder).
 */
class RecordActivity
{
    public function __construct(private ActivityRecorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $response = $next($request);

        try {
            $this->recorder->recordRequest($request, $response, $startedAt);
        } catch (\Throwable $e) {
            // El análisis nunca debe romper la API.
            report($e);
        }

        return $response;
    }
}
