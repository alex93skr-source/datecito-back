<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Connection;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    private const PAGE_SIZE = 50;

    /**
     * Mensajes en orden cronológico. `after` trae sólo los nuevos (para sondeo ligero);
     * `before` pagina hacia atrás.
     */
    public function index(Request $request, Connection $connection): AnonymousResourceCollection
    {
        Gate::authorize('view', $connection);

        $request->validate([
            'after' => ['nullable', 'string', 'ulid'],
            'before' => ['nullable', 'string', 'ulid'],
        ]);

        $messages = $connection->messages()
            ->when($request->filled('after'), fn ($q) => $q->where('id', '>', $request->string('after')))
            ->when($request->filled('before'), fn ($q) => $q->where('id', '<', $request->string('before')))
            ->orderByDesc('id')
            ->limit(self::PAGE_SIZE)
            ->get()
            ->reverse()
            ->values();

        return MessageResource::collection($messages);
    }

    public function store(StoreMessageRequest $request, Connection $connection, MessageService $messages): MessageResource
    {
        Gate::authorize('message', $connection);

        return MessageResource::make($messages->send($connection, $request->user(), $request->string('body')));
    }
}
