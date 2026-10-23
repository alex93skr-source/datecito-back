<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea cuentas suspendidas o baneadas y revoca su token actual.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && in_array($user->status, [UserStatus::Suspended, UserStatus::Banned], true)) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'Tu cuenta no está disponible.', 'code' => 'account_unavailable'], 403);
        }

        return $next($request);
    }
}
