<?php

namespace App\Http\Middleware;

use App\Services\MembershipService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige membresía activa (salvo durante el lanzamiento gratuito).
 * El perfil, los datos y las conversaciones se conservan aunque venza.
 */
class EnsureMembershipAccess
{
    public function __construct(private MembershipService $membership) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->membership->hasAccess($request->user())) {
            return response()->json(['message' => 'Activa tu membresía para continuar.', 'code' => 'membership_required'], 402);
        }

        return $next($request);
    }
}
