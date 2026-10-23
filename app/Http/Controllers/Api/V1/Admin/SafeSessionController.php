<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SafeSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SafeSessionResource;
use App\Models\SafeSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Sesiones de Datecito Seguro: las que piden ayuda o no hicieron check-in van primero.
 */
class SafeSessionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', Rule::in([...array_column(SafeSessionStatus::cases(), 'value'), 'all', 'attention'])]]);
        $status = $request->input('status', 'attention');

        $sessions = SafeSession::query()
            ->when($status === 'attention', fn (Builder $q) => $q->whereIn('status', [SafeSessionStatus::Help, SafeSessionStatus::Overdue, SafeSessionStatus::Active]))
            ->when(! in_array($status, ['attention', 'all'], true), fn (Builder $q) => $q->where('status', $status))
            ->with(['user', 'datecito.connection.userOne', 'datecito.connection.userTwo'])
            ->orderByRaw("FIELD(status, 'help', 'overdue', 'active', 'ended')")
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return SafeSessionResource::collection($sessions);
    }
}
