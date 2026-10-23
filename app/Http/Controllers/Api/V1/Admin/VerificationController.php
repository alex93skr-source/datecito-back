<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewVerificationRequest;
use App\Http\Resources\Admin\VerificationResource;
use App\Models\VerificationRequest;
use App\Services\Admin\AuditLog;
use App\Services\VerificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Cola de verificaciones con selfie.
 */
class VerificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', Rule::in([...array_column(VerificationStatus::cases(), 'value'), 'all'])]]);
        $status = $request->input('status', VerificationStatus::Pending->value);

        $verifications = VerificationRequest::query()
            ->when($status !== 'all', fn (Builder $q) => $q->where('status', $status))
            ->with(['user.photos', 'reviewer'])
            ->when($status === VerificationStatus::Pending->value, fn (Builder $q) => $q->oldest(), fn (Builder $q) => $q->latest())
            ->paginate(30)
            ->withQueryString();

        return VerificationResource::collection($verifications);
    }

    public function review(ReviewVerificationRequest $request, VerificationRequest $verification, VerificationService $service, AuditLog $audit): Response
    {
        abort_if($verification->status !== VerificationStatus::Pending, 409);
        $service->review($verification, $request->user(), $request->boolean('approve'));
        $audit->recordOnUser($request->boolean('approve') ? 'verification.approved' : 'verification.rejected', $verification->user, ['verification_id' => $verification->id]);

        return response()->noContent();
    }
}
