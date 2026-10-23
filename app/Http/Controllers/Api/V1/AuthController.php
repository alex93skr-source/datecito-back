<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EmailCodePurpose;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\MeResource;
use App\Models\User;
use App\Services\EmailCodeService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const TOKEN_TTL_DAYS = 30;

    public function __construct(private EmailCodeService $codes) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        // Código propio para que la app ofrezca iniciar sesión o recuperar la contraseña.
        $taken = fn () => response()->json([
            'message' => 'Ya existe una cuenta con este correo. Inicia sesión o recupera tu contraseña.',
            'code' => 'email_taken',
        ], 409);

        if (User::query()->where('email', $request->string('email'))->exists()) {
            return $taken();
        }

        try {
            $user = User::create($request->safe()->only(['email', 'password']));
        } catch (UniqueConstraintViolationException) {
            return $taken();
        }
        $this->codes->send($user->email, EmailCodePurpose::VerifyEmail);

        return $this->tokenResponse($user, $request->string('device_name'), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email'))->first();

        // Se verifica contra un hash falso si no existe, para no revelar qué correos están registrados.
        static $dummyHash = null;
        $dummyHash ??= Hash::make(Str::random(32));
        $valid = Hash::check($request->string('password'), $user?->password ?? $dummyHash);

        if ($user === null || ! $valid) {
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no coinciden.']);
        }

        if (in_array($user->status, [UserStatus::Suspended, UserStatus::Banned], true)) {
            return response()->json(['message' => 'Tu cuenta no está disponible.', 'code' => 'account_unavailable'], 403);
        }

        return $this->tokenResponse($user, $request->string('device_name'));
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function verifyEmail(VerifyEmailRequest $request): MeResource|JsonResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            if (! $this->codes->consume($user->email, EmailCodePurpose::VerifyEmail, $request->string('code'))) {
                throw ValidationException::withMessages(['code' => 'El código no es válido o ya venció.']);
            }

            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return MeResource::make($user->load('photos'));
    }

    public function resendCode(Request $request): Response
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $this->codes->sendUnlessRecent($user->email, EmailCodePurpose::VerifyEmail);
        }

        return response()->noContent();
    }

    /**
     * Siempre responde igual, exista o no la cuenta (evita enumerar correos).
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        if (User::query()->where('email', $request->string('email'))->exists()) {
            $this->codes->send($request->string('email'), EmailCodePurpose::ResetPassword);
        }

        return response()->json(['message' => 'Si existe una cuenta con ese correo, te enviamos un código.'], 202);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email'))->first();

        if ($user === null || ! $this->codes->consume($user->email, EmailCodePurpose::ResetPassword, $request->string('code'))) {
            throw ValidationException::withMessages(['code' => 'El código no es válido o ya venció.']);
        }

        $user->forceFill(['password' => $request->string('password'), 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        // Cierra todas las sesiones abiertas con la contraseña anterior.
        $user->tokens()->delete();

        return response()->json(['message' => 'Listo. Ya puedes entrar con tu nueva contraseña.']);
    }

    private function tokenResponse(User $user, string $deviceName, int $status = 200): JsonResponse
    {
        $token = $user->createToken($deviceName, ['*'], now()->addDays(self::TOKEN_TTL_DAYS));

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => MeResource::make($user->load('photos')),
        ], $status);
    }
}
