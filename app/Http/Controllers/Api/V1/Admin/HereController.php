<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\HereSessionResource;
use App\Models\HereSession;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sesiones vivas de "Aquí y ahora". No hay historial: las vencidas se borran.
 */
class HereController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $sessions = HereSession::query()->live()->with('user')->latest()->paginate(50);

        return HereSessionResource::collection($sessions)->additional([
            'meta' => [
                'places' => HereSession::query()->live()->selectRaw('place as label, COUNT(*) as value')->groupBy('place')->orderByDesc('value')->limit(15)->get(),
            ],
        ]);
    }
}
