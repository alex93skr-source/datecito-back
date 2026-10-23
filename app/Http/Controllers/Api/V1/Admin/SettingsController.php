<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Configuración vigente del producto (sólo lectura: se cambia por variables de entorno) y salud del sistema.
 */
class SettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $oldestPending = DB::table('jobs')->min('created_at');

        return response()->json([
            'data' => [
                'app' => ['name' => config('app.name'), 'env' => config('app.env'), 'url' => config('app.url'), 'timezone' => config('app.timezone')],
                'flags' => [
                    'launch_free' => (bool) config('datecito.launch_free'),
                    'fake_purchases' => (bool) config('datecito.fake_purchases'),
                    'auto_verify' => (bool) config('datecito.auto_verify'),
                ],
                'pricing' => config('datecito.pricing'),
                'limits' => config('datecito.limits'),
                'catalog' => [
                    'report_reasons' => config('datecito.catalog.report_reasons'),
                    'plan_kinds' => config('datecito.catalog.plan_kinds'),
                    'genders' => config('datecito.catalog.genders'),
                    'intents' => config('datecito.catalog.intents'),
                ],
                'roles' => User::query()->selectRaw('role, COUNT(*) as n')->groupBy('role')->pluck('n', 'role'),
                'health' => [
                    'queue_pending' => DB::table('jobs')->count(),
                    'queue_failed' => DB::table('failed_jobs')->count(),
                    'queue_oldest_pending_seconds' => $oldestPending === null ? 0 : max(0, now()->timestamp - (int) $oldestPending),
                    'last_event_at' => ActivityEvent::query()->max('occurred_at'),
                    'events_total' => ActivityEvent::query()->count(),
                ],
            ],
        ]);
    }
}
