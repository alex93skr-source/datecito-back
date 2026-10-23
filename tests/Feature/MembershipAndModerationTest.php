<?php

namespace Tests\Feature;

use App\Contracts\PurchaseVerifier;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MembershipAndModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_after_launch_a_subscription_unlocks_the_app(): void
    {
        config(['datecito.launch_free' => false]);
        $this->signIn();

        $this->getJson('/api/v1/discover')->assertStatus(402);
        $this->postJson('/api/v1/membership/purchases', ['store' => 'fake', 'product_id' => 'datecito_yearly', 'purchase_token' => 'abc'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan', 'yearly');
        $this->getJson('/api/v1/discover')->assertOk();
    }

    public function test_fake_purchases_are_refused_when_disabled(): void
    {
        config(['datecito.fake_purchases' => false]);
        $this->app->forgetInstance(PurchaseVerifier::class);
        $this->signIn();

        $this->postJson('/api/v1/membership/purchases', ['store' => 'fake', 'product_id' => 'datecito_monthly', 'purchase_token' => 'abc'])
            ->assertStatus(503)
            ->assertJsonPath('code', 'store_not_configured');
    }

    public function test_regular_users_cannot_use_moderation_tools(): void
    {
        $this->signIn();

        $this->getJson('/api/v1/admin/reports')->assertForbidden();
    }

    public function test_moderators_can_read_report_details_and_suspend_accounts(): void
    {
        $reported = User::factory()->create();
        $reporter = User::factory()->create();
        $this->signIn($reporter);
        $this->postJson('/api/v1/reports', ['user_id' => $reported->id, 'reason' => 'Perfil falso o spam', 'details' => 'Fotos robadas']);

        $moderator = User::factory()->create();
        $moderator->forceFill(['role' => UserRole::Moderator])->save();
        $this->signIn($moderator);

        $this->getJson('/api/v1/admin/reports')->assertOk()->assertJsonPath('data.0.details', 'Fotos robadas');
        $this->putJson("/api/v1/admin/users/{$reported->id}/status", ['status' => 'suspended'])->assertNoContent();
        $this->assertSame(UserStatus::Suspended, $reported->fresh()->status);
    }

    public function test_moderators_cannot_change_an_admin(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::Admin])->save();
        $moderator = User::factory()->create();
        $moderator->forceFill(['role' => UserRole::Moderator])->save();
        $this->signIn($moderator);

        $this->putJson("/api/v1/admin/users/{$admin->id}/status", ['status' => 'banned'])->assertForbidden();
    }

    public function test_selfie_verification_waits_for_review(): void
    {
        $user = $this->signIn();

        $this->postJson('/api/v1/verification', ['selfie' => UploadedFile::fake()->image('selfie.jpg', 800, 800), 'pose' => 'Haz la señal de paz con los dedos'])
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('verified', false);

        app(VerificationService::class)->review($user->verificationRequests()->sole(), null, approve: true);
        $this->assertTrue($user->fresh()->isVerified());
    }
}
