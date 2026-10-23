<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PeriodRequest;
use App\Http\Resources\Admin\ActivityEventResource;
use App\Models\ActivityEvent;
use App\Services\Admin\MetricsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Explorador de comportamiento: todos los eventos, filtrables.
 */
class EventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', Rule::in(['api', 'client', 'system'])],
            'user_id' => ['nullable', 'string', 'ulid'],
            'platform' => ['nullable', Rule::in(['ios', 'android', 'web'])],
            'errors' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $events = ActivityEvent::query()
            ->when($request->filled('name'), fn (Builder $q) => $q->where('name', 'like', $request->string('name').'%'))
            ->when($request->filled('category'), fn (Builder $q) => $q->where('category', $request->string('category')))
            ->when($request->filled('user_id'), fn (Builder $q) => $q->where('user_id', $request->string('user_id')))
            ->when($request->filled('platform'), fn (Builder $q) => $q->where('platform', $request->string('platform')))
            ->when($request->boolean('errors'), fn (Builder $q) => $q->where('status', '>=', 400))
            ->when($request->filled('from'), fn (Builder $q) => $q->where('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->where('occurred_at', '<', $request->date('to')->addDay()))
            ->with('user')
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return ActivityEventResource::collection($events);
    }

    /**
     * Nombres de evento más frecuentes en el periodo (para el selector y el ranking).
     */
    public function names(PeriodRequest $request, MetricsService $metrics): JsonResponse
    {
        return response()->json(['data' => $metrics->topEvents($request->period(), 200, $request->input('category'))]);
    }

    /**
     * Rendimiento de la API por ruta: latencia y errores.
     */
    public function performance(PeriodRequest $request): JsonResponse
    {
        $period = $request->period();

        $rows = ActivityEvent::query()
            ->where('category', ActivityEvent::CATEGORY_API)
            ->between($period->from, $period->to)
            ->selectRaw('name, COUNT(*) as count, ROUND(AVG(duration_ms)) as avg_ms, MAX(duration_ms) as max_ms, SUM(status >= 500) as errors_5xx, SUM(status >= 400 AND status < 500) as errors_4xx')
            ->groupBy('name')
            ->orderByDesc('count')
            ->limit(100)
            ->get()
            ->map(fn (ActivityEvent $row) => [
                'name' => $row->name,
                'count' => (int) $row->count,
                'avg_ms' => (int) $row->avg_ms,
                'max_ms' => (int) $row->max_ms,
                'errors_5xx' => (int) $row->errors_5xx,
                'errors_4xx' => (int) $row->errors_4xx,
            ]);

        return response()->json(['data' => ['period' => $period->toArray(), 'routes' => $rows]]);
    }
}
