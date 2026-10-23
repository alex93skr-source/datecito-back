<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ConnectionSource;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ConnectionResource;
use App\Http\Resources\Admin\MessageResource;
use App\Models\Connection;
use App\Models\Report;
use App\Services\Admin\AuditLog;
use App\Services\ConnectionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Conexiones (matches). Los mensajes sólo se abren con un reporte de por medio o siendo admin,
 * y cada lectura queda en la bitácora.
 */
class ConnectionController extends Controller
{
    public function __construct(private AuditLog $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'user_id' => ['nullable', 'string', 'ulid'],
            'source' => ['nullable', Rule::enum(ConnectionSource::class)],
            'active' => ['nullable', 'boolean'],
            'with_messages' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $connections = Connection::query()
            ->when($request->filled('user_id'), fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('user_one_id', $request->string('user_id'))->orWhere('user_two_id', $request->string('user_id'))))
            ->when($request->filled('source'), fn (Builder $q) => $q->where('source', $request->string('source')))
            ->when($request->filled('active'), fn (Builder $q) => $request->boolean('active') ? $q->whereNull('ended_at') : $q->whereNotNull('ended_at'))
            ->when($request->filled('with_messages'), fn (Builder $q) => $request->boolean('with_messages') ? $q->whereNotNull('last_message_at') : $q->whereNull('last_message_at'))
            ->when($request->filled('from'), fn (Builder $q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->where('created_at', '<', $request->date('to')->addDay()))
            ->with(['userOne', 'userTwo'])
            ->withCount(['messages', 'datecitos'])
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return ConnectionResource::collection($connections);
    }

    public function show(Connection $connection): ConnectionResource
    {
        return ConnectionResource::make(
            $connection->load(['userOne', 'userTwo', 'datecitos' => fn ($q) => $q->latest()->withCount('feedback')])->loadCount(['messages', 'datecitos']),
        );
    }

    public function messages(Request $request, Connection $connection): AnonymousResourceCollection
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        abort_unless($this->mayReadMessages($request, $connection), 403, 'Sólo se pueden leer conversaciones con un reporte de por medio.');

        $this->audit->record('messages.viewed', $connection, ['users' => [$connection->user_one_id, $connection->user_two_id]], $request->input('reason'));

        return MessageResource::collection($connection->messages()->latest('id')->paginate(50)->withQueryString());
    }

    public function end(Request $request, Connection $connection, ConnectionService $connections): Response
    {
        $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $connections->end($connection, $request->user());
        $this->audit->record('connection.ended', $connection, ['users' => [$connection->user_one_id, $connection->user_two_id]], $request->input('note'));

        return response()->noContent();
    }

    private function mayReadMessages(Request $request, Connection $connection): bool
    {
        if ($request->user()->isAdmin()) {
            return true;
        }

        $ids = [$connection->user_one_id, $connection->user_two_id];

        return Report::query()->whereIn('reported_id', $ids)->whereIn('reporter_id', $ids)->exists()
            || Report::query()->whereIn('reported_id', $ids)->whereIn('status', ['open', 'reviewing'])->exists();
    }
}
