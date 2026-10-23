<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Eliminación definitiva de la cuenta y de todos sus datos (requisito de Google Play).
 */
class AccountService
{
    public function __construct(private PhotoService $photos, private ActivityRecorder $activity) {}

    public function delete(User $user, ?string $reason = null): void
    {
        // Se registra antes de borrar: el evento queda sin persona (user_id nulo) pero cuenta para la métrica de bajas.
        $this->activity->recordSystem(null, 'account.deleted', array_filter([
            'reason' => $reason ?? 'self',
            'account_age_days' => (int) $user->created_at?->diffInDays(now()),
            'platform' => $user->last_platform,
        ]));

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });

        $this->photos->deleteAllFor($user);
    }
}
