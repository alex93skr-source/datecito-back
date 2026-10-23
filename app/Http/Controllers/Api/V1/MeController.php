<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\ChangePasswordRequest;
use App\Http\Requests\Me\DeleteAccountRequest;
use App\Http\Requests\Me\UpdateFiltersRequest;
use App\Http\Requests\Me\UpdateLocationRequest;
use App\Http\Requests\Me\UpdateProfileRequest;
use App\Http\Requests\Me\UpdateSettingsRequest;
use App\Http\Resources\MeResource;
use App\Services\AccountService;
use App\Support\Geo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MeController extends Controller
{
    public function show(Request $request): MeResource
    {
        return MeResource::make($request->user()->load('photos'));
    }

    public function update(UpdateProfileRequest $request): MeResource
    {
        $user = $request->user();
        $user->update($request->profileData());

        return MeResource::make($user->load('photos'));
    }

    public function updateSettings(UpdateSettingsRequest $request): MeResource
    {
        $user = $request->user();
        $user->update($request->validated());

        return MeResource::make($user->load('photos'));
    }

    public function updateFilters(UpdateFiltersRequest $request): MeResource
    {
        $user = $request->user();
        $user->update(['filters' => $request->validated()]);

        return MeResource::make($user->load('photos'));
    }

    /**
     * Sólo se guarda una ubicación aproximada (≈ 1 km).
     */
    public function updateLocation(UpdateLocationRequest $request): Response
    {
        $request->user()->forceFill([
            'approx_lat' => Geo::approximate((float) $request->input('lat'), 2),
            'approx_lng' => Geo::approximate((float) $request->input('lng'), 2),
            'zone' => $request->input('zone') ?? $request->user()->zone,
        ])->save();

        return response()->noContent();
    }

    public function changePassword(ChangePasswordRequest $request): Response
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->string('password')])->save();
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        return response()->noContent();
    }

    public function destroy(DeleteAccountRequest $request, AccountService $accounts): Response
    {
        $accounts->delete($request->user());

        return response()->noContent();
    }
}
