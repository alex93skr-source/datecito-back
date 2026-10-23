<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige correo verificado antes de interactuar con otras personas.
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasVerifiedEmail()) {
            return response()->json(['message' => 'Verifica tu correo para continuar.', 'code' => 'email_unverified'], 403);
        }

        return $next($request);
    }
}
