<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\StoreTrustedContactRequest;
use App\Http\Requests\TargetUserRequest;
use App\Http\Resources\PersonResource;
use App\Http\Resources\TrustedContactResource;
use App\Models\Block;
use App\Models\TrustedContact;
use App\Models\User;
use App\Services\SafetyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Bloqueos, reportes y contactos de confianza. Disponible siempre, aun sin membresía.
 */
class SafetyController extends Controller
{
    public function __construct(private SafetyService $safety) {}

    public function blocks(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->whereIn('id', Block::query()->select('blocked_id')->where('blocker_id', $request->user()->id))
            ->with('photos')
            ->get();

        return PersonResource::collection($users);
    }

    public function block(TargetUserRequest $request): Response
    {
        $this->safety->block($request->user(), User::query()->findOrFail($request->string('user_id')));

        return response()->noContent();
    }

    public function unblock(Request $request, User $user): Response
    {
        $this->safety->unblock($request->user(), $user);

        return response()->noContent();
    }

    public function report(StoreReportRequest $request): JsonResponse
    {
        $reported = $request->filled('user_id') ? User::query()->find($request->string('user_id')) : null;
        $this->safety->report(
            $request->user(),
            $reported,
            $request->string('reason'),
            $request->input('details'),
            $request->boolean('also_block'),
        );

        return response()->json(['message' => 'Gracias por avisarnos. Lo revisaremos con cuidado y discreción.'], 201);
    }

    public function contacts(Request $request): AnonymousResourceCollection
    {
        return TrustedContactResource::collection($request->user()->trustedContacts()->oldest()->get());
    }

    public function addContact(StoreTrustedContactRequest $request): TrustedContactResource
    {
        return TrustedContactResource::make($this->safety->addContact($request->user(), $request->string('name'), $request->string('phone')));
    }

    public function removeContact(TrustedContact $contact): Response
    {
        Gate::authorize('delete', $contact);
        $contact->delete();

        return response()->noContent();
    }
}
