<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVerificationRequest;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => $user->verification_status->value,
            'verified' => $user->isVerified(),
        ]);
    }

    public function store(StoreVerificationRequest $request, VerificationService $verification): JsonResponse
    {
        $verification->submit($request->user(), $request->file('selfie'), $request->string('pose'));
        $user = $request->user()->fresh();

        return response()->json([
            'status' => $user->verification_status->value,
            'verified' => $user->isVerified(),
        ], 201);
    }
}
