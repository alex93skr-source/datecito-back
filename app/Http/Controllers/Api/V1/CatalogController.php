<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Catálogos (intereses, géneros musicales, etc.) y precios vigentes.
 */
class CatalogController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'catalog' => config('datecito.catalog'),
            'limits' => collect(config('datecito.limits'))->only(['photos_max', 'photos_min', 'interests_max', 'genres_max', 'prompts_max', 'trusted_contacts_max', 'message_max_length', 'here_minutes']),
            'pricing' => config('datecito.pricing'),
        ]);
    }
}
