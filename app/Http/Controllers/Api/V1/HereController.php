<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHereSessionRequest;
use App\Http\Requests\TargetUserRequest;
use App\Http\Resources\HereSessionResource;
use App\Http\Resources\PersonResource;
use App\Models\User;
use App\Services\HereService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HereController extends Controller
{
    public function __construct(private HereService $here) {}

    public function current(Request $request): HereSessionResource|JsonResponse
    {
        $session = $this->here->current($request->user());

        return $session === null ? response()->json(['data' => null]) : HereSessionResource::make($session);
    }

    public function store(StoreHereSessionRequest $request): HereSessionResource
    {
        return HereSessionResource::make($this->here->start(
            $request->user(),
            $request->string('place'),
            (float) $request->input('lat'),
            (float) $request->input('lng'),
            $request->integer('minutes'),
            $request->boolean('verified_only'),
        ));
    }

    public function destroy(Request $request): Response
    {
        $this->here->stop($request->user());

        return response()->noContent();
    }

    public function people(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $greeted = $this->here->greetedIds($viewer);

        return response()->json([
            'data' => $this->here->peopleNear($viewer)->map(fn (User $person) => [
                'person' => PersonResource::make($person)->resolve($request),
                'greeted' => in_array($person->id, $greeted, true),
            ])->values(),
        ]);
    }

    public function greet(TargetUserRequest $request): JsonResponse
    {
        $result = $this->here->greet($request->user(), User::query()->findOrFail($request->string('user_id')));

        return response()->json([
            'matched' => $result['matched'],
            'connection_id' => $result['connection']?->id,
        ]);
    }

    public function hide(TargetUserRequest $request): Response
    {
        $this->here->hide($request->user(), User::query()->findOrFail($request->string('user_id')));

        return response()->noContent();
    }
}
