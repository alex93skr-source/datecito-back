<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PurchaseStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseRequest;
use App\Services\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function __construct(private MembershipService $membership) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->membership->summary($request->user())]);
    }

    public function purchase(PurchaseRequest $request): JsonResponse
    {
        $this->membership->purchase(
            $request->user(),
            PurchaseStore::from($request->string('store')),
            $request->string('product_id'),
            $request->string('purchase_token'),
        );

        return response()->json(['data' => $this->membership->summary($request->user()->fresh())], 201);
    }
}
