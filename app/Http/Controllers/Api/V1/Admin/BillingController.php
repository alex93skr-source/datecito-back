<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PurchaseStore;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PeriodRequest;
use App\Http\Resources\Admin\PurchaseResource;
use App\Http\Resources\Admin\SubscriptionResource;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Services\Admin\MetricsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Monetización: membresías y paquetes de Super likes.
 */
class BillingController extends Controller
{
    public function overview(PeriodRequest $request, MetricsService $metrics): JsonResponse
    {
        return response()->json(['data' => $metrics->billing($request->period())]);
    }

    public function subscriptions(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(SubscriptionStatus::class)],
            'plan' => ['nullable', Rule::enum(SubscriptionPlan::class)],
            'store' => ['nullable', Rule::enum(PurchaseStore::class)],
            'current' => ['nullable', 'boolean'],
            'granted' => ['nullable', 'boolean'],
            'user_id' => ['nullable', 'string', 'ulid'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $subscriptions = Subscription::query()
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('plan'), fn (Builder $q) => $q->where('plan', $request->string('plan')))
            ->when($request->filled('store'), fn (Builder $q) => $q->where('store', $request->string('store')))
            ->when($request->filled('current'), fn (Builder $q) => $request->boolean('current')
                ? $q->where('status', SubscriptionStatus::Active)->where('expires_at', '>', now())
                : $q->where(fn (Builder $w) => $w->where('status', '!=', SubscriptionStatus::Active->value)->orWhere('expires_at', '<=', now())))
            ->when($request->filled('granted'), fn (Builder $q) => $q->where('store_reference', $request->boolean('granted') ? 'like' : 'not like', 'crm:%'))
            ->when($request->filled('user_id'), fn (Builder $q) => $q->where('user_id', $request->string('user_id')))
            ->when($request->filled('q'), fn (Builder $q) => $q->whereHas('user', fn (Builder $u) => $u->where('email', 'like', $request->string('q').'%')->orWhere('name', 'like', '%'.$request->string('q').'%')))
            ->with('user')
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return SubscriptionResource::collection($subscriptions);
    }

    public function purchases(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'store' => ['nullable', Rule::enum(PurchaseStore::class)],
            'user_id' => ['nullable', 'string', 'ulid'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $purchases = Purchase::query()
            ->when($request->filled('store'), fn (Builder $q) => $q->where('store', $request->string('store')))
            ->when($request->filled('user_id'), fn (Builder $q) => $q->where('user_id', $request->string('user_id')))
            ->when($request->filled('q'), fn (Builder $q) => $q->whereHas('user', fn (Builder $u) => $u->where('email', 'like', $request->string('q').'%')->orWhere('name', 'like', '%'.$request->string('q').'%')))
            ->with('user')
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return PurchaseResource::collection($purchases);
    }
}
