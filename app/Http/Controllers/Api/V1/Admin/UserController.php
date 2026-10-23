<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListUsersRequest;
use App\Http\Resources\Admin\ActivityEventResource;
use App\Http\Resources\Admin\AdminActionResource;
use App\Http\Resources\Admin\ConnectionResource;
use App\Http\Resources\Admin\PurchaseResource;
use App\Http\Resources\Admin\ReportResource;
use App\Http\Resources\Admin\SubscriptionResource;
use App\Http\Resources\Admin\UserDetailResource;
use App\Http\Resources\Admin\UserSummaryResource;
use App\Models\AdminAction;
use App\Models\Connection;
use App\Models\Report;
use App\Models\Swipe;
use App\Models\User;
use App\Models\UserTag;
use App\Services\Admin\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Personas: búsqueda, ficha completa y todo su historial.
 */
class UserController extends Controller
{
    public function index(ListUsersRequest $request): AnonymousResourceCollection
    {
        $users = $this->filtered($request)
            ->orderBy($request->input('sort', 'created_at'), $request->input('dir', 'desc'))
            ->orderBy('id')
            ->paginate((int) $request->input('per_page', 25))
            ->withQueryString();

        return UserSummaryResource::collection($users);
    }

    /**
     * CSV con los mismos filtros del listado (hasta 20 000 filas).
     */
    public function export(ListUsersRequest $request, AuditLog $audit): StreamedResponse
    {
        $audit->record('users.exported', null, $request->validated());
        $query = $this->filtered($request)->orderBy('created_at', 'desc')->orderBy('id')->limit(20000);

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'nombre', 'correo', 'edad', 'género', 'zona', 'estado', 'rol', 'correo_verificado', 'perfil_verificado', 'fotos', 'swipes', 'reportes_recibidos', 'membresía', 'plataforma', 'última_actividad', 'registro']);

            foreach ($query->cursor() as $user) {
                $subscription = $user->subscriptions->first();
                fputcsv($out, [
                    $user->id, $user->name, $user->email, $user->age(), $user->gender, $user->zone, $user->status->value, $user->role->value,
                    $user->hasVerifiedEmail() ? 'sí' : 'no', $user->isVerified() ? 'sí' : 'no', $user->photos_count, $user->swipes_count, $user->reports_received_count,
                    $subscription?->plan->value ?? '', $user->last_platform, $user->last_seen_at?->toDateTimeString(), $user->created_at->toDateTimeString(),
                ]);
            }

            fclose($out);
        }, 'personas-'.now()->format('Ymd-Hi').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(User $user): UserDetailResource
    {
        $user->load([
            'photos',
            'tags',
            'notes' => fn ($q) => $q->latest('pinned')->latest()->with('author'),
            'subscriptions' => fn ($q) => $q->latest('expires_at')->limit(10),
            'purchases' => fn ($q) => $q->latest()->limit(10),
            'verificationRequests' => fn ($q) => $q->latest()->limit(5)->with(['reviewer', 'user.photos']),
        ]);

        return UserDetailResource::make($user);
    }

    public function events(Request $request, User $user): AnonymousResourceCollection
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', Rule::in(['api', 'client', 'system'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $events = $user->activityEvents()
            ->when($request->filled('name'), fn (Builder $q) => $q->where('name', 'like', $request->string('name').'%'))
            ->when($request->filled('category'), fn (Builder $q) => $q->where('category', $request->string('category')))
            ->when($request->filled('from'), fn (Builder $q) => $q->where('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->where('occurred_at', '<', $request->date('to')->addDay()))
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return ActivityEventResource::collection($events);
    }

    public function connections(User $user): AnonymousResourceCollection
    {
        $connections = Connection::query()
            ->forUser($user)
            ->with(['userOne', 'userTwo'])
            ->withCount(['messages', 'datecitos'])
            ->latest()
            ->paginate(25);

        return ConnectionResource::collection($connections);
    }

    public function swipes(User $user): JsonResponse
    {
        $recent = fn (string $column, string $relation) => Swipe::query()
            ->where($column, $user->id)
            ->with($relation.':id,name,email,status')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (Swipe $swipe) => [
                'id' => $swipe->id,
                'type' => $swipe->type->value,
                'person' => UserSummaryResource::mini($swipe->{$relation}),
                'created_at' => $swipe->created_at->toIso8601String(),
            ]);

        return response()->json([
            'data' => [
                'given' => $recent('user_id', 'target'),
                'received' => $recent('target_id', 'user'),
                'given_by_day' => Swipe::query()->where('user_id', $user->id)->where('created_at', '>=', now()->subDays(30))
                    ->selectRaw('DATE(created_at) as date, COUNT(*) as value')->groupBy('date')->orderBy('date')->get(),
            ],
        ]);
    }

    public function reports(User $user): JsonResponse
    {
        $with = ['reporter', 'reported' => fn ($q) => $q->withCount('reportsReceived'), 'handler'];

        return response()->json([
            'data' => [
                'received' => ReportResource::collection(Report::query()->where('reported_id', $user->id)->with($with)->latest()->limit(50)->get()),
                'made' => ReportResource::collection(Report::query()->where('reporter_id', $user->id)->with($with)->latest()->limit(50)->get()),
            ],
        ]);
    }

    public function billing(User $user): JsonResponse
    {
        return response()->json([
            'data' => [
                'subscriptions' => SubscriptionResource::collection($user->subscriptions()->latest('expires_at')->get()),
                'purchases' => PurchaseResource::collection($user->purchases()->latest()->get()),
                'has_current' => $user->subscriptions()->where('status', SubscriptionStatus::Active)->where('expires_at', '>', now())->exists(),
            ],
        ]);
    }

    public function audit(User $user): AnonymousResourceCollection
    {
        $actions = AdminAction::query()
            ->where('subject_type', 'User')
            ->where('subject_id', $user->id)
            ->with('actor')
            ->latest('id')
            ->paginate(30);

        return AdminActionResource::collection($actions);
    }

    /**
     * Etiquetas en uso, con cuántas personas tiene cada una.
     */
    public function tags(): JsonResponse
    {
        return response()->json([
            'data' => UserTag::query()->selectRaw('tag, COUNT(*) as users')->groupBy('tag')->orderByDesc('users')->orderBy('tag')->get(),
        ]);
    }

    /**
     * @return Builder<User>
     */
    private function filtered(ListUsersRequest $request): Builder
    {
        $q = trim((string) $request->input('q'));

        return User::query()
            ->withCount(['photos', 'swipes', 'reportsReceived'])
            ->with(['tags', 'subscriptions' => fn ($s) => $s->where('status', SubscriptionStatus::Active)->where('expires_at', '>', now())->latest('expires_at')])
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w
                ->where('email', 'like', $q.'%')
                ->orWhere('name', 'like', '%'.$q.'%')
                ->orWhere('id', $q)))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('role'), fn (Builder $query) => $query->where('role', $request->string('role')))
            ->when($request->filled('verification'), fn (Builder $query) => $query->where('verification_status', $request->string('verification')))
            ->when($request->filled('email_verified'), fn (Builder $query) => $request->boolean('email_verified') ? $query->whereNotNull('email_verified_at') : $query->whereNull('email_verified_at'))
            ->when($request->filled('gender'), fn (Builder $query) => $query->where('gender', $request->string('gender')))
            ->when($request->filled('zone'), fn (Builder $query) => $query->where('zone', $request->string('zone')))
            ->when($request->filled('platform'), fn (Builder $query) => $query->where('last_platform', $request->string('platform')))
            ->when($request->filled('membership'), fn (Builder $query) => $query->{$request->input('membership') === 'active' ? 'whereHas' : 'whereDoesntHave'}(
                'subscriptions', fn (Builder $s) => $s->where('status', SubscriptionStatus::Active)->where('expires_at', '>', now()),
            ))
            ->when($request->filled('has_photos'), fn (Builder $query) => $request->boolean('has_photos') ? $query->has('photos') : $query->doesntHave('photos'))
            ->when($request->filled('tag'), fn (Builder $query) => $query->whereHas('tags', fn (Builder $t) => $t->where('tag', mb_strtolower($request->string('tag')))))
            ->when($request->filled('created_from'), fn (Builder $query) => $query->where('created_at', '>=', $request->date('created_from')))
            ->when($request->filled('created_to'), fn (Builder $query) => $query->where('created_at', '<', $request->date('created_to')->addDay()))
            ->when($request->filled('seen_from'), fn (Builder $query) => $query->where('last_seen_at', '>=', $request->date('seen_from')))
            ->when($request->filled('seen_to'), fn (Builder $query) => $query->where('last_seen_at', '<', $request->date('seen_to')->addDay()))
            ->when($request->filled('min_reports'), fn (Builder $query) => $query->has('reportsReceived', '>=', (int) $request->input('min_reports')));
    }
}
