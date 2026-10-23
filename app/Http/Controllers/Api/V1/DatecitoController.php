<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DatecitoStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DatecitoFeedbackRequest;
use App\Http\Requests\StoreDatecitoRequest;
use App\Http\Resources\DatecitoResource;
use App\Models\Connection;
use App\Models\Datecito;
use App\Services\DatecitoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DatecitoController extends Controller
{
    public function __construct(private DatecitoService $datecitos) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $me = $request->user();

        $list = Datecito::query()
            ->whereHas('connection', fn (Builder $q) => $q->forUser($me)->active())
            ->where('status', '!=', DatecitoStatus::Cancelled)
            ->with(['connection.userOne.photos', 'connection.userTwo.photos', 'feedback'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return DatecitoResource::collection($list);
    }

    public function show(Datecito $datecito): DatecitoResource
    {
        Gate::authorize('view', $datecito);

        return DatecitoResource::make($datecito->load(['connection.userOne.photos', 'connection.userTwo.photos', 'feedback']));
    }

    public function store(StoreDatecitoRequest $request, Connection $connection): DatecitoResource
    {
        Gate::authorize('message', $connection);
        $datecito = $this->datecitos->propose($connection, $request->user(), $request->validated());

        return DatecitoResource::make($datecito->load(['connection.userOne.photos', 'connection.userTwo.photos', 'feedback']));
    }

    public function confirm(Request $request, Datecito $datecito): DatecitoResource
    {
        Gate::authorize('update', $datecito);

        return DatecitoResource::make($this->datecitos->confirm($datecito, $request->user())->load(['connection.userOne.photos', 'connection.userTwo.photos', 'feedback']));
    }

    public function cancel(Datecito $datecito): DatecitoResource
    {
        Gate::authorize('view', $datecito);

        return DatecitoResource::make($this->datecitos->cancel($datecito)->load(['connection.userOne.photos', 'connection.userTwo.photos', 'feedback']));
    }

    public function feedback(DatecitoFeedbackRequest $request, Datecito $datecito): Response
    {
        Gate::authorize('view', $datecito);
        $this->datecitos->leaveFeedback($datecito, $request->user(), $request->validated());

        return response()->noContent();
    }
}
