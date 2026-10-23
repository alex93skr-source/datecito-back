<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminActionResource;
use App\Http\Resources\Admin\UserSummaryResource;
use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Bitácora de lo que hace el equipo.
 */
class AuditController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'actor_id' => ['nullable', 'string', 'ulid'],
            'action' => ['nullable', 'string', 'max:60'],
            'subject_id' => ['nullable', 'string', 'max:40'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $actions = AdminAction::query()
            ->when($request->filled('actor_id'), fn (Builder $q) => $q->where('actor_id', $request->string('actor_id')))
            ->when($request->filled('action'), fn (Builder $q) => $q->where('action', 'like', $request->string('action').'%'))
            ->when($request->filled('subject_id'), fn (Builder $q) => $q->where('subject_id', $request->string('subject_id')))
            ->when($request->filled('from'), fn (Builder $q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->where('created_at', '<', $request->date('to')->addDay()))
            ->with('actor')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        // Nombre de la persona afectada, en una sola consulta.
        $userIds = $actions->getCollection()->where('subject_type', 'User')->pluck('subject_id')->unique()->all();
        $users = User::query()->whereKey($userIds)->get()->keyBy('id');
        $actions->getCollection()->each(fn (AdminAction $action) => $action->subject = $action->subject_type === 'User' ? UserSummaryResource::mini($users->get($action->subject_id)) : null);

        return AdminActionResource::collection($actions)->additional([
            'meta' => ['actions' => AdminAction::query()->selectRaw('action, COUNT(*) as count')->groupBy('action')->orderByDesc('count')->get()],
        ]);
    }
}
