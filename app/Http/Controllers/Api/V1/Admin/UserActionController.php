<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SubscriptionPlan;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustSuperLikesRequest;
use App\Http\Requests\Admin\GrantMembershipRequest;
use App\Http\Requests\Admin\SetUserRoleRequest;
use App\Http\Requests\Admin\SyncUserTagsRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\Admin\SubscriptionResource;
use App\Http\Resources\Admin\UserSummaryResource;
use App\Models\Photo;
use App\Models\User;
use App\Services\Admin\AdminUserService;
use App\Services\Admin\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Acciones del equipo sobre una cuenta. Todas quedan en la bitácora.
 */
class UserActionController extends Controller
{
    public function __construct(private AdminUserService $users, private AuditLog $audit) {}

    /**
     * Edición editorial (por ejemplo, quitar un texto ofensivo o esconder el perfil de Descubrir).
     */
    public function update(UpdateUserRequest $request, User $user): UserSummaryResource
    {
        $this->guardStaff($request, $user);
        $changes = $request->safe()->except('note');
        $before = collect($changes)->map(fn ($value, string $key) => $user->{$key})->all();

        $user->forceFill($changes)->save();
        $this->audit->recordOnUser('user.profile_edited', $user, ['before' => $before, 'after' => $changes], $request->input('note'));

        return UserSummaryResource::make($user->loadCount(['photos', 'swipes', 'reportsReceived'])->load('tags'));
    }

    public function status(Request $request, User $user): Response
    {
        $request->validate(['status' => ['required', Rule::enum(UserStatus::class)], 'note' => ['nullable', 'string', 'max:500']]);
        $this->guardStaff($request, $user);

        $this->users->setStatus($user, $request->enum('status', UserStatus::class), $request->input('note'));

        return response()->noContent();
    }

    public function role(SetUserRoleRequest $request, User $user): Response
    {
        // Nadie se quita a sí mismo el rol de admin: evita dejar el CRM sin administradores.
        abort_if($request->user()->is($user), 422, 'No puedes cambiar tu propio rol.');

        $this->users->setRole($user, $request->enum('role', UserRole::class), $request->input('note'));

        return response()->noContent();
    }

    public function verification(Request $request, User $user): Response
    {
        $request->validate(['verified' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:500']]);

        $this->users->setVerified($user, $request->boolean('verified'), $request->input('note'));

        return response()->noContent();
    }

    public function superLikes(AdjustSuperLikesRequest $request, User $user): JsonResponse
    {
        $balance = $this->users->adjustSuperLikes($user, (int) $request->input('delta'), $request->input('note'));

        return response()->json(['data' => ['super_likes_balance' => $balance]]);
    }

    public function grantMembership(GrantMembershipRequest $request, User $user): SubscriptionResource
    {
        $subscription = $this->users->grantMembership($user, $request->enum('plan', SubscriptionPlan::class), (int) $request->input('days'), $request->input('note'));

        return SubscriptionResource::make($subscription);
    }

    public function revokeMembership(Request $request, User $user): Response
    {
        $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        abort_unless($this->users->revokeMembership($user, $request->input('note')), 409, 'La persona no tiene una membresía vigente.');

        return response()->noContent();
    }

    public function removePhoto(Request $request, User $user, Photo $photo): Response
    {
        abort_if($photo->user_id !== $user->id, 404);
        $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $this->users->removePhoto($user, $photo, $request->input('note'));

        return response()->noContent();
    }

    public function logout(Request $request, User $user): JsonResponse
    {
        $this->guardStaff($request, $user);
        $count = $this->users->logoutEverywhere($user, $request->input('note'));

        return response()->json(['data' => ['sessions_closed' => $count]]);
    }

    public function destroy(Request $request, User $user): Response
    {
        $request->validate(['note' => ['required', 'string', 'max:500']]);
        abort_if($request->user()->is($user), 422, 'No puedes eliminar tu propia cuenta desde el CRM.');
        abort_if($user->isAdmin(), 403, 'Primero quita el rol de admin a esta cuenta.');

        $this->users->deleteAccount($user, $request->input('note'));

        return response()->noContent();
    }

    public function tags(SyncUserTagsRequest $request, User $user): JsonResponse
    {
        $tags = $request->tags();
        $user->tags()->whereNotIn('tag', $tags)->delete();
        foreach ($tags as $tag) {
            $user->tags()->firstOrCreate(['tag' => $tag], ['created_by' => $request->user()->id]);
        }
        $this->audit->recordOnUser('user.tags', $user, ['tags' => $tags]);

        return response()->json(['data' => ['tags' => $tags]]);
    }

    /**
     * Nadie se modera a sí mismo; sólo un admin toca a otra persona del equipo.
     */
    private function guardStaff(Request $request, User $user): void
    {
        abort_if($request->user()->is($user), 422, 'No puedes aplicar esta acción sobre tu propia cuenta.');
        abort_if($user->canModerate() && ! $request->user()->isAdmin(), 403, 'Sólo un admin puede modificar a otra persona del equipo.');
    }
}
