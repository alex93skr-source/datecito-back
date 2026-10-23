<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SwipeType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PersonResource;
use App\Models\Connection;
use App\Models\Swipe;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Quién te dio like": personas que te dieron like y aún no has decidido.
 *
 * Sin membresía sólo se entrega cuántas son y unas siluetas (inicial, edad, zona),
 * nunca ids ni fotos: así no hay forma de ver el perfil por otro camino.
 */
class LikesController extends Controller
{
    private const MAX = 100;

    private const TEASERS = 6;

    public function __invoke(Request $request, MembershipService $membership): JsonResponse
    {
        $me = $request->user();
        $connected = Connection::query()->where('user_one_id', $me->id)->pluck('user_two_id')
            ->merge(Connection::query()->where('user_two_id', $me->id)->pluck('user_one_id'));

        $swipes = Swipe::query()
            ->where('target_id', $me->id)
            ->whereIn('type', [SwipeType::Like, SwipeType::Super])
            ->whereNotIn('user_id', $me->blockedUserIds())
            ->whereNotIn('user_id', Swipe::query()->select('target_id')->where('user_id', $me->id))
            ->whereNotIn('user_id', $connected)
            ->whereHas('user', fn (Builder $q) => $q->reachable()->whereHas('photos'))
            ->with('user.photos')
            // Super likes primero, luego los más recientes.
            ->orderByRaw("CASE WHEN type = 'super' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(self::MAX)
            ->get();

        $meta = ['count' => $swipes->count(), 'locked' => ! $membership->hasAccess($me)];

        if ($meta['locked']) {
            $meta['teasers'] = $swipes->take(self::TEASERS)->map(fn (Swipe $swipe) => [
                'initial' => mb_strtoupper(mb_substr($swipe->user->name ?? '', 0, 1)),
                'age' => $swipe->user->age(),
                'zone' => $swipe->user->zone,
                'verified' => $swipe->user->isVerified(),
                'super' => $swipe->type === SwipeType::Super,
            ])->values();

            return response()->json(['data' => [], 'meta' => $meta]);
        }

        $people = $swipes->map(function (Swipe $swipe): User {
            $swipe->user->setAttribute('liked_me', true);
            $swipe->user->setAttribute('super_liked_me', $swipe->type === SwipeType::Super);

            return $swipe->user;
        });

        return PersonResource::collection($people)->additional(['meta' => $meta])->response();
    }
}
