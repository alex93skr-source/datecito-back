<?php

namespace Tests\Feature;

use App\Enums\EmailCodePurpose;
use App\Enums\UserStatus;
use App\Mail\EmailCodeMail;
use App\Models\User;
use App\Services\EmailCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function issueCode(string $email, EmailCodePurpose $purpose): string
    {
        $code = null;
        Mail::fake();
        app(EmailCodeService::class)->send($email, $purpose);
        Mail::assertQueued(EmailCodeMail::class, function (EmailCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    public function test_register_creates_an_unverified_account_and_sends_a_code(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'Ana@Correo.com',
            'password' => 'secreta123',
            'accepted_terms' => true,
            'device_name' => 'android',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'ana@correo.com')
            ->assertJsonPath('user.email_verified', false)
            ->assertJsonMissingPath('user.password');
        $this->assertStringContainsString('|dtc_', $response->json('token'));
        Mail::assertQueued(EmailCodeMail::class, fn (EmailCodeMail $mail) => $mail->hasTo('ana@correo.com'));
    }

    public function test_register_requires_terms_and_a_strong_password(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'email' => 'ana@correo.com',
            'password' => 'corta',
            'device_name' => 'android',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password', 'accepted_terms']);
    }

    public function test_register_with_an_existing_email_offers_to_sign_in(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'ana@correo.com']);

        $this->postJson('/api/v1/auth/register', [
            'email' => 'Ana@Correo.com',
            'password' => 'Segura2026',
            'accepted_terms' => true,
            'device_name' => 'test',
        ])->assertStatus(409)->assertJsonPath('code', 'email_taken');

        $this->assertDatabaseCount('users', 1);
        Mail::assertNothingQueued();
    }

    public function test_register_ignores_privileged_fields(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/register', [
            'email' => 'ana@correo.com',
            'password' => 'secreta123',
            'accepted_terms' => true,
            'device_name' => 'android',
            'role' => 'admin',
            'verified_at' => now()->toIso8601String(),
            'email_verified_at' => now()->toIso8601String(),
        ])->assertCreated();

        $user = User::query()->where('email', 'ana@correo.com')->firstOrFail();
        $this->assertSame('user', $user->role->value);
        $this->assertNull($user->verified_at);
        $this->assertNull($user->email_verified_at);
    }

    public function test_login_with_wrong_password_returns_a_generic_error(): void
    {
        User::factory()->create(['email' => 'ana@correo.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'ana@correo.com', 'password' => 'incorrecta1', 'device_name' => 'android'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'El correo o la contraseña no coinciden.']);

        $this->postJson('/api/v1/auth/login', ['email' => 'nadie@correo.com', 'password' => 'incorrecta1', 'device_name' => 'android'])
            ->assertJsonValidationErrors(['email' => 'El correo o la contraseña no coinciden.']);
    }

    public function test_login_returns_a_token(): void
    {
        User::factory()->create(['email' => 'ana@correo.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'ANA@correo.com', 'password' => 'password1', 'device_name' => 'android'])
            ->assertOk()
            ->assertJsonStructure(['token', 'expires_at', 'user' => ['id', 'email']]);
    }

    public function test_banned_accounts_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'ana@correo.com'])->forceFill(['status' => UserStatus::Banned])->save();

        $this->postJson('/api/v1/auth/login', ['email' => 'ana@correo.com', 'password' => 'password1', 'device_name' => 'android'])
            ->assertForbidden()
            ->assertJsonPath('code', 'account_unavailable');
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/login', ['email' => 'ana@correo.com', 'password' => 'x', 'device_name' => 'a']);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'ana@correo.com', 'password' => 'x', 'device_name' => 'a'])
            ->assertTooManyRequests();
    }

    public function test_email_is_verified_with_the_right_code_only(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $this->issueCode($user->email, EmailCodePurpose::VerifyEmail);
        $this->signIn($user);
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->postJson('/api/v1/auth/email/verify', ['code' => $wrong])
            ->assertJsonValidationErrors(['code' => 'El código no es válido o ya venció.']);
        $this->assertNull($user->fresh()->email_verified_at);

        $this->postJson('/api/v1/auth/email/verify', ['code' => $code])->assertOk()->assertJsonPath('data.email_verified', true);
    }

    public function test_codes_are_locked_after_too_many_attempts(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $this->issueCode($user->email, EmailCodePurpose::VerifyEmail);
        $service = app(EmailCodeService::class);
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, 5) as $attempt) {
            $this->assertFalse($service->consume($user->email, EmailCodePurpose::VerifyEmail, $wrong));
        }

        $this->assertFalse($service->consume($user->email, EmailCodePurpose::VerifyEmail, $code));
    }

    public function test_unverified_accounts_cannot_use_the_app(): void
    {
        $this->signIn(User::factory()->unverified()->create());

        $this->getJson('/api/v1/discover')->assertForbidden()->assertJsonPath('code', 'email_unverified');
    }

    public function test_resending_a_code_waits_a_minute_between_emails(): void
    {
        Mail::fake();
        $this->signIn(User::factory()->unverified()->create());

        $this->postJson('/api/v1/auth/email/resend')->assertNoContent();
        $this->postJson('/api/v1/auth/email/resend')->assertNoContent();
        Mail::assertQueuedCount(1);

        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/email/resend')->assertNoContent();
        Mail::assertQueuedCount(2);
    }

    public function test_requests_without_a_token_are_rejected(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_forgot_password_does_not_reveal_whether_an_account_exists(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nadie@correo.com'])->assertAccepted();

        Mail::assertNothingQueued();
    }

    public function test_password_reset_revokes_every_session(): void
    {
        $user = User::factory()->create();
        $user->createToken('old');
        $code = $this->issueCode($user->email, EmailCodePurpose::ResetPassword);

        $this->postJson('/api/v1/auth/password/reset', ['email' => $user->email, 'code' => $code, 'password' => 'nueva12345'])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'nueva12345', 'device_name' => 'a'])->assertOk();
    }
}
