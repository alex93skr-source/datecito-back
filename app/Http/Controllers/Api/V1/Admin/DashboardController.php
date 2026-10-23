<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PeriodRequest;
use App\Services\Admin\MetricsService;
use Illuminate\Http\JsonResponse;

/**
 * Métricas globales del CRM.
 */
class DashboardController extends Controller
{
    public function __construct(private MetricsService $metrics) {}

    public function overview(PeriodRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->metrics->overview($request->period())]);
    }

    public function series(PeriodRequest $request): JsonResponse
    {
        $metric = (string) $request->input('metric', 'signups');

        return response()->json(['data' => $this->metrics->timeseries($request->period(), $metric, $request->input('name'))]);
    }

    /**
     * Varias series de una vez para no hacer N peticiones desde el panel.
     */
    public function multiSeries(PeriodRequest $request): JsonResponse
    {
        $request->validate(['metrics' => ['required', 'array', 'max:8'], 'metrics.*' => ['string', 'in:'.implode(',', array_keys(MetricsService::SERIES))]]);
        $period = $request->period();

        return response()->json([
            'data' => collect($request->input('metrics'))
                ->mapWithKeys(fn (string $metric) => [$metric => $this->metrics->timeseries($period, $metric)])
                ->all(),
        ]);
    }

    public function funnel(PeriodRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->metrics->funnel($request->period())]);
    }

    public function retention(PeriodRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->metrics->retention((int) $request->input('weeks', 8))]);
    }

    public function breakdowns(PeriodRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->metrics->breakdowns($request->period())]);
    }

    public function live(): JsonResponse
    {
        return response()->json(['data' => $this->metrics->live()]);
    }
}
