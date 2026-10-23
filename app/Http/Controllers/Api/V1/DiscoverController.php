<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PersonResource;
use App\Services\DailyPacingService;
use App\Services\DiscoveryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DiscoverController extends Controller
{
    public function __invoke(Request $request, DiscoveryService $discovery, DailyPacingService $pacing): AnonymousResourceCollection
    {
        $viewer = $request->user();
        $deck = $discovery->deck($viewer);
        $pace = $pacing->summary($viewer);

        return PersonResource::collection($deck)->additional(['meta' => [
            ...$pace,
            // Por qué viene vacío: ya vio sus perfiles de hoy, o no hay más personas con sus filtros.
            'empty_reason' => $deck->isNotEmpty() ? null : ($pace['profiles_left'] === 0 ? 'daily_limit' : 'exhausted'),
        ]]);
    }
}
