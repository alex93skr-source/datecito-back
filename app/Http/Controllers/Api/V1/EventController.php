<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventsRequest;
use App\Services\ActivityRecorder;
use Illuminate\Http\Response;

/**
 * Recibe eventos de comportamiento enviados por la app (pantallas vistas, acciones de interfaz).
 */
class EventController extends Controller
{
    public function store(StoreEventsRequest $request, ActivityRecorder $recorder): Response
    {
        $recorder->recordClientEvents($request->user(), $request, $request->events());

        return response()->noContent(202);
    }
}
