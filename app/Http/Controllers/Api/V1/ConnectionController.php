<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DatecitoStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConnectionResource;
use App\Models\Connection;
use App\Services\ConnectionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ConnectionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $me = $request->user();
        $blocked = $me->blockedUserIds();

        $connections = Connection::query()
            ->forUser($me)
            ->active()
            ->whereNotIn('user_one_id', $blocked)
            ->whereNotIn('user_two_id', $blocked)
            ->with(['userOne.photos', 'userTwo.photos', 'latestMessage'])
            ->withCount(['datecitos' => fn (Builder $q) => $q->where('status', '!=', DatecitoStatus::Cancelled)])
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return ConnectionResource::collection($connections);
    }

    public function show(Connection $connection): ConnectionResource
    {
        Gate::authorize('view', $connection);

        return ConnectionResource::make($connection->load(['userOne.photos', 'userTwo.photos', 'latestMessage'])
            ->loadCount(['datecitos' => fn (Builder $q) => $q->where('status', '!=', DatecitoStatus::Cancelled)]));
    }

    /**
     * "Quitar match".
     */
    public function destroy(Request $request, Connection $connection, ConnectionService $connections): Response
    {
        Gate::authorize('end', $connection);
        $connections->end($connection, $request->user());

        return response()->noContent();
    }
}
