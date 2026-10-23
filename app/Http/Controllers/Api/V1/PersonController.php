<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PersonResource;
use App\Models\User;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    /**
     * Perfil público. Con bloqueo o cuenta no disponible responde 404 (no revela el motivo).
     */
    public function show(Request $request, User $user): PersonResource
    {
        $viewer = $request->user();

        abort_if(
            $viewer->is($user) || ! $user->isActive() || ! $user->hasVerifiedEmail() || $viewer->hasBlockWith($user),
            404,
        );

        return PersonResource::make($user->load('photos'));
    }
}
