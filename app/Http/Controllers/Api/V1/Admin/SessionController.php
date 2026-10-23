<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Quién soy dentro del CRM y qué puedo hacer.
 */
class SessionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'permissions' => [
                    'moderate' => $user->can('moderate'),
                    'administer' => $user->can('administer'),
                ],
            ],
        ]);
    }
}
