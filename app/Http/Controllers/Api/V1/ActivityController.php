<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DatecitoStatus;
use App\Enums\SwipeType;
use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\Datecito;
use App\Models\Swipe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Actividad reciente: nuevos matches, mensajes y datecitos.
 * Los textos no exponen contenido de mensajes (pensado también para notificaciones).
 */
class ActivityController extends Controller
{
    private const PER_PAGE = 10;

    public function __invoke(Request $request): JsonResponse
    {
        $me = $request->user();
        $since = now()->subDays(14);
        $blocked = $me->blockedUserIds();

        $connections = Connection::query()
            ->forUser($me)
            ->active()
            ->whereNotIn('user_one_id', $blocked)
            ->whereNotIn('user_two_id', $blocked)
            ->where(fn (Builder $q) => $q->where('created_at', '>=', $since)->orWhere('last_message_at', '>=', $since))
            ->with(['userOne', 'userTwo', 'latestMessage'])
            ->get();

        $items = collect();

        foreach ($connections as $connection) {
            $other = $connection->other($me);

            if ($connection->created_at >= $since) {
                $items->push(['type' => 'match', 'title' => 'Tienes un nuevo match', 'text' => 'Tú y '.$other->name.' se eligieron.', 'connection_id' => $connection->id, 'person_id' => $other->id, 'created_at' => $connection->created_at]);
            }

            $last = $connection->latestMessage;
            if ($last !== null && $last->sender_id !== $me->id && $last->created_at >= $since) {
                $items->push(['type' => 'message', 'title' => 'Te dejaron un mensaje', 'text' => $other->name.' te escribió.', 'connection_id' => $connection->id, 'person_id' => $other->id, 'created_at' => $last->created_at]);
            }
        }

        Datecito::query()
            ->whereIn('connection_id', $connections->modelKeys())
            ->where('updated_at', '>=', $since)
            ->where('status', '!=', DatecitoStatus::Cancelled)
            ->get()
            ->each(function (Datecito $datecito) use ($items, $me): void {
                $mine = $datecito->proposer_id === $me->id;
                $confirmed = $datecito->status === DatecitoStatus::Confirmed;

                if (! $confirmed && $mine) {
                    return;
                }

                $items->push([
                    'type' => 'datecito',
                    'title' => $confirmed ? 'Tu datecito está confirmado' : 'Te propusieron un datecito',
                    'text' => $datecito->place.' · '.$datecito->when_label,
                    'datecito_id' => $datecito->id,
                    'created_at' => $datecito->updated_at,
                ]);
            });

        // Super likes recibidos de personas que aún no has decidido (aparecen primero en tu Descubrir).
        Swipe::query()
            ->where('target_id', $me->id)
            ->where('type', SwipeType::Super)
            ->where('created_at', '>=', $since)
            ->whereNotIn('user_id', $blocked)
            ->whereNotIn('user_id', Swipe::query()->select('target_id')->where('user_id', $me->id))
            ->whereNotIn('user_id', $connections->map(fn (Connection $c) => $c->other($me)->id))
            ->with('user')
            ->get()
            ->each(fn (Swipe $swipe) => $items->push([
                'type' => 'super_like',
                'title' => 'Te dieron un Super like ⭐',
                'text' => $swipe->user->name.' quiere conocerte.',
                'person_id' => $swipe->user_id,
                'created_at' => $swipe->created_at,
            ]));

        // De 10 en 10 para no mandar todo de una vez.
        $page = max(1, $request->integer('page', 1));
        $sorted = $items->sortByDesc('created_at')->values();
        $slice = $sorted->forPage($page, self::PER_PAGE);

        return response()->json([
            'data' => $slice->map(fn (array $item) => [...$item, 'created_at' => $item['created_at']->toIso8601String()])->values(),
            'meta' => ['page' => $page, 'has_more' => $sorted->count() > $page * self::PER_PAGE],
        ]);
    }
}
