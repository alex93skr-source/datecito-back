<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\DatecitoStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DatecitoResource;
use App\Models\Connection;
use App\Models\Datecito;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class DatecitoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(DatecitoStatus::class)],
            'kind' => ['nullable', 'string', 'max:20'],
            'zone' => ['nullable', 'string', 'max:60'],
            'user_id' => ['nullable', 'string', 'ulid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'upcoming' => ['nullable', 'boolean'],
        ]);

        $datecitos = Datecito::query()
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('kind'), fn (Builder $q) => $q->where('kind', $request->string('kind')))
            ->when($request->filled('zone'), fn (Builder $q) => $q->where('zone', $request->string('zone')))
            ->when($request->filled('user_id'), fn (Builder $q) => $q->whereIn('connection_id', Connection::query()
                ->where(fn (Builder $w) => $w->where('user_one_id', $request->string('user_id'))->orWhere('user_two_id', $request->string('user_id')))
                ->select('id')))
            ->when($request->filled('from'), fn (Builder $q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->where('created_at', '<', $request->date('to')->addDay()))
            ->when($request->boolean('upcoming'), fn (Builder $q) => $q->where('scheduled_on', '>=', today()))
            ->with(['connection.userOne', 'connection.userTwo'])
            ->withCount('feedback')
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return DatecitoResource::collection($datecitos);
    }

    public function show(Datecito $datecito): DatecitoResource
    {
        return DatecitoResource::make($datecito->load(['connection.userOne', 'connection.userTwo'])->loadCount('feedback'));
    }
}
