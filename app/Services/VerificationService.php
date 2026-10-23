<?php

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Verificación de perfil con selfie, revisada por el equipo de moderación.
 */
class VerificationService
{
    public function __construct(private PhotoService $photos) {}

    public function submit(User $user, UploadedFile $selfie, ?string $pose = null): VerificationRequest
    {
        $key = $this->photos->storeSelfie($user, $selfie);

        $request = DB::transaction(function () use ($user, $key, $pose): VerificationRequest {
            $request = new VerificationRequest(['selfie_key' => $key, 'pose' => $pose, 'status' => VerificationStatus::Pending]);
            $request->forceFill(['user_id' => $user->id])->save();
            $user->forceFill(['verification_status' => VerificationStatus::Pending])->save();

            return $request;
        });

        if (config('datecito.auto_verify') && app()->environment('local', 'testing')) {
            $this->review($request, null, approve: true);
        }

        return $request;
    }

    public function review(VerificationRequest $request, ?User $reviewer, bool $approve): void
    {
        DB::transaction(function () use ($request, $reviewer, $approve): void {
            $status = $approve ? VerificationStatus::Approved : VerificationStatus::Rejected;

            $request->forceFill(['status' => $status, 'reviewed_by' => $reviewer?->id, 'reviewed_at' => now()])->save();
            $request->user->forceFill([
                'verification_status' => $status,
                'verified_at' => $approve ? now() : null,
            ])->save();
        });
    }
}
