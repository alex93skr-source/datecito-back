<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SwipeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSwipeRequest;
use App\Models\User;
use App\Services\DailyPacingService;
use App\Services\MembershipService;
use App\Services\SwipeService;
use Illuminate\Http\JsonResponse;

class SwipeController extends Controller
{
    public function store(StoreSwipeRequest $request, SwipeService $swipes, MembershipService $membership, DailyPacingService $pacing): JsonResponse
    {
        $viewer = $request->user();
        $target = User::query()->findOrFail($request->string('user_id'));
        $result = $swipes->swipe($viewer, $target, SwipeType::from($request->string('type')));

        return response()->json([
            'matched' => $result['matched'],
            'connection_id' => $result['connection']?->id,
            'super_likes' => $membership->summary($viewer->fresh())['super_likes'],
            'likes_left' => $pacing->likesLeft($viewer),
        ]);
    }
}
