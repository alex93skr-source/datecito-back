<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSafeSessionRequest;
use App\Http\Resources\SafeSessionResource;
use App\Models\Datecito;
use App\Models\SafeSession;
use App\Services\SafeSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Datecito Seguro. Disponible siempre, aun sin membresía.
 */
class SafeSessionController extends Controller
{
    public function __construct(private SafeSessionService $sessions) {}

    public function current(Request $request): SafeSessionResource|JsonResponse
    {
        $session = $this->sessions->current($request->user());

        return $session === null ? response()->json(['data' => null]) : SafeSessionResource::make($session);
    }

    public function store(StoreSafeSessionRequest $request): SafeSessionResource
    {
        $datecito = Datecito::query()->findOrFail($request->string('datecito_id'));
        Gate::authorize('view', $datecito);

        $session = $this->sessions->start(
            $request->user(),
            $datecito,
            $request->input('contact_ids'),
            $request->integer('check_in_minutes'),
            $request->boolean('share_location'),
        );

        return SafeSessionResource::make($session);
    }

    public function checkIn(SafeSession $session): SafeSessionResource
    {
        Gate::authorize('update', $session);

        return SafeSessionResource::make($this->sessions->checkIn($session));
    }

    public function help(SafeSession $session): SafeSessionResource
    {
        Gate::authorize('update', $session);

        return SafeSessionResource::make($this->sessions->requestHelp($session));
    }

    public function end(SafeSession $session): SafeSessionResource
    {
        Gate::authorize('update', $session);

        return SafeSessionResource::make($this->sessions->end($session));
    }
}
