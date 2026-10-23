<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Equipo: quién tiene rol de moderación o administración y cuánto ha actuado.
 */
class StaffController extends Controller
{
    public function index(): JsonResponse
    {
        $staff = User::query()->whereIn('role', [UserRole::Moderator, UserRole::Admin])->orderBy('role')->orderBy('name')->get();
        $counts = AdminAction::query()->whereIn('actor_id', $staff->pluck('id'))->selectRaw('actor_id, COUNT(*) as total, MAX(created_at) as last_at, SUM(created_at >= ?) as last_30d', [now()->subDays(30)])->groupBy('actor_id')->get()->keyBy('actor_id');

        return response()->json([
            'data' => $staff->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'status' => $user->status->value,
                'last_seen_at' => $user->last_seen_at?->toIso8601String(),
                'actions_total' => (int) ($counts[$user->id]->total ?? 0),
                'actions_30d' => (int) ($counts[$user->id]->last_30d ?? 0),
                'last_action_at' => isset($counts[$user->id]->last_at) ? now()->parse($counts[$user->id]->last_at)->toIso8601String() : null,
                'created_at' => $user->created_at->toIso8601String(),
            ])->values(),
        ]);
    }
}
