<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ActivityEvent;
use App\Models\AdminAction;
use App\Models\Connection;
use App\Models\Report;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrmTest extends TestCase
{
    use RefreshDatabase;

    private function staff(UserRole $role = UserRole::Moderator): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        $this->signIn($user);

        return $user;
    }

    public function test_regular_users_cannot_enter_the_crm(): void
    {
        $this->signIn();

        $this->getJson('/api/v1/admin/me')->assertForbidden();
        $this->getJson('/api/v1/admin/dashboard/overview')->assertForbidden();
        $this->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_session_reports_role_and_permissions(): void
    {
        $this->staff();
        $this->getJson('/api/v1/admin/me')->assertOk()->assertJsonPath('data.permissions.moderate', true)->assertJsonPath('data.permissions.administer', false);

        $this->staff(UserRole::Admin);
        $this->getJson('/api/v1/admin/me')->assertOk()->assertJsonPath('data.permissions.administer', true);
    }

    public function test_overview_counts_the_period_and_compares_with_the_previous_one(): void
    {
        $this->staff();
        $recent = User::factory()->count(3)->create(['created_at' => now()->subDays(2)]);
        User::factory()->create(['created_at' => now()->subDays(10)]);
        ActivityEvent::factory()->create(['user_id' => $recent[0]->id, 'occurred_at' => now()->subDay()]);
        ActivityEvent::factory()->create(['user_id' => $recent[1]->id, 'occurred_at' => now()->subDay()]);

        $response = $this->getJson('/api/v1/admin/dashboard/overview?from='.now()->subDays(6)->toDateString().'&to='.now()->toDateString())->assertOk();

        // 3 en el periodo + la propia cuenta del equipo creada hoy.
        $response->assertJsonPath('data.kpis.signups.value', 4)
            ->assertJsonPath('data.kpis.signups.previous', 1)
            ->assertJsonPath('data.kpis.active_users.value', 2)
            ->assertJsonPath('data.period.interval', 'day')
            ->assertJsonStructure(['data' => ['totals', 'activity' => ['dau', 'wau', 'mau'], 'kpis', 'rates']]);
    }

    public function test_series_funnel_retention_and_breakdowns_have_the_expected_shape(): void
    {
        $this->staff();
        $user = User::factory()->withPhoto()->create();
        $user->swipes()->create(['target_id' => User::factory()->create()->id, 'type' => 'like']);
        ActivityEvent::factory()->create(['user_id' => $user->id]);

        $series = $this->getJson('/api/v1/admin/dashboard/series?metric=signups&from='.now()->subDays(2)->toDateString())->assertOk()->json('data.series');
        $this->assertCount(3, $series);
        $this->assertSame(now()->toDateString(), $series[2]['date']);
        $this->assertGreaterThanOrEqual(3, $series[2]['value']);

        $this->getJson('/api/v1/admin/dashboard/series?metric=invalid')->assertUnprocessable();
        $this->getJson('/api/v1/admin/dashboard/multi-series?metrics[]=swipes&metrics[]=matches')->assertOk()->assertJsonStructure(['data' => ['swipes' => ['series'], 'matches' => ['series']]]);

        $funnel = $this->getJson('/api/v1/admin/dashboard/funnel')->assertOk()->json('data.steps');
        $this->assertSame('registered', $funnel[0]['key']);
        $this->assertSame(1, collect($funnel)->firstWhere('key', 'first_swipe')['count']);
        $this->assertSame(1, collect($funnel)->firstWhere('key', 'has_photo')['count']);

        $retention = $this->getJson('/api/v1/admin/dashboard/retention?weeks=4')->assertOk()->json('data');
        $this->assertSame(4, $retention['weeks']);
        $this->assertCount(4, $retention['cohorts']);
        $this->assertGreaterThan(0, end($retention['cohorts'])['retention'][0]);

        $this->getJson('/api/v1/admin/dashboard/breakdowns')->assertOk()->assertJsonStructure(['data' => ['users' => ['gender', 'age', 'zone', 'membership'], 'behavior' => ['swipe_types', 'top_events', 'heatmap']]]);
        $this->getJson('/api/v1/admin/dashboard/live')->assertOk()->assertJsonStructure(['data' => ['online_15m', 'today', 'recent_events']]);
    }

    public function test_users_can_be_searched_filtered_sorted_and_exported(): void
    {
        $this->staff();
        $ana = User::factory()->withPhoto()->create(['name' => 'Ana', 'email' => 'ana@example.com']);
        $ana->forceFill(['status' => UserStatus::Suspended])->save();
        $ana->tags()->create(['tag' => 'vip']);
        User::factory()->create(['name' => 'Beto']);

        $this->getJson('/api/v1/admin/users?q=ana')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'ana@example.com')->assertJsonPath('data.0.tags.0', 'vip');
        $this->getJson('/api/v1/admin/users?status=suspended')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/users?tag=vip')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/users?has_photos=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.photos_count', 1);
        $this->getJson('/api/v1/admin/users?sort=name&dir=asc')->assertOk()->assertJsonPath('data.0.name', 'Ana');
        $this->getJson('/api/v1/admin/users?sort=hack')->assertUnprocessable();

        $export = $this->get('/api/v1/admin/users/export?status=suspended')->assertOk();
        $this->assertStringContainsString('text/csv', $export->headers->get('content-type'));
        $this->assertStringContainsString('ana@example.com', $export->streamedContent());
        $this->assertDatabaseHas('admin_actions', ['action' => 'users.exported']);
    }

    public function test_user_detail_includes_profile_stats_and_history(): void
    {
        $this->staff();
        $user = User::factory()->withPhoto()->create();
        $other = User::factory()->create();
        $connection = Connection::factory()->between($user, $other)->create();
        $connection->messages()->create(['sender_id' => $user->id, 'body' => 'hola']);
        $user->swipes()->create(['target_id' => $other->id, 'type' => 'super']);
        ActivityEvent::factory()->count(3)->create(['user_id' => $user->id, 'name' => 'discover']);

        $this->getJson("/api/v1/admin/users/{$user->id}")->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.stats.swipes_given.super', 1)
            ->assertJsonPath('data.stats.connections.total', 1)
            ->assertJsonPath('data.stats.messages_sent', 1)
            ->assertJsonPath('data.stats.activity.events', 3)
            ->assertJsonCount(1, 'data.photos')
            ->assertJsonStructure(['data' => ['profile', 'settings', 'membership', 'notes', 'subscriptions']]);

        $this->getJson("/api/v1/admin/users/{$user->id}/events?name=disc")->assertOk()->assertJsonCount(3, 'data');
        $this->getJson("/api/v1/admin/users/{$user->id}/connections")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.messages_count', 1);
        $this->getJson("/api/v1/admin/users/{$user->id}/swipes")->assertOk()->assertJsonPath('data.given.0.type', 'super');
        $this->getJson("/api/v1/admin/users/{$user->id}/billing")->assertOk()->assertJsonPath('data.has_current', false);
    }

    public function test_moderators_suspend_accounts_and_everything_is_audited(): void
    {
        $moderator = $this->staff();
        $user = User::factory()->create();

        $this->putJson("/api/v1/admin/users/{$user->id}/status", ['status' => 'suspended', 'note' => 'Spam'])->assertNoContent();

        $this->assertSame(UserStatus::Suspended, $user->fresh()->status);
        $action = AdminAction::query()->where('action', 'user.status')->sole();
        $this->assertSame($moderator->id, $action->actor_id);
        $this->assertSame($user->id, $action->subject_id);
        $this->assertSame('suspended', $action->changes['to']);
        $this->assertSame('Spam', $action->note);
        $this->assertDatabaseHas('activity_events', ['user_id' => $user->id, 'name' => 'admin.user.status']);

        $this->getJson("/api/v1/admin/users/{$user->id}/audit")->assertOk()->assertJsonPath('data.0.action', 'user.status')->assertJsonPath('data.0.actor.id', $moderator->id);
        $this->getJson('/api/v1/admin/audit?action=user.')->assertOk()->assertJsonPath('data.0.subject.id', $user->id);
    }

    public function test_moderators_cannot_touch_themselves_other_staff_or_admin_only_actions(): void
    {
        $moderator = $this->staff();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::Admin])->save();
        $user = User::factory()->create();

        $this->putJson("/api/v1/admin/users/{$moderator->id}/status", ['status' => 'banned'])->assertUnprocessable();
        $this->putJson("/api/v1/admin/users/{$admin->id}/status", ['status' => 'banned'])->assertForbidden();
        $this->putJson("/api/v1/admin/users/{$user->id}/role", ['role' => 'admin'])->assertForbidden();
        $this->postJson("/api/v1/admin/users/{$user->id}/super-likes", ['delta' => 5])->assertForbidden();
        $this->postJson("/api/v1/admin/users/{$user->id}/membership", ['plan' => 'monthly', 'days' => 30])->assertForbidden();
        $this->deleteJson("/api/v1/admin/users/{$user->id}", ['note' => 'x'])->assertForbidden();
        $this->assertSame(UserRole::User, $user->fresh()->role);
    }

    public function test_admins_manage_roles_membership_super_likes_and_deletion(): void
    {
        config(['datecito.launch_free' => false]);
        $admin = $this->staff(UserRole::Admin);
        $user = User::factory()->create();

        $this->putJson("/api/v1/admin/users/{$user->id}/role", ['role' => 'moderator'])->assertNoContent();
        $this->assertSame(UserRole::Moderator, $user->fresh()->role);
        $this->putJson("/api/v1/admin/users/{$admin->id}/role", ['role' => 'user'])->assertUnprocessable();

        $this->postJson("/api/v1/admin/users/{$user->id}/super-likes", ['delta' => 5, 'note' => 'Cortesía'])->assertOk()->assertJsonPath('data.super_likes_balance', 5);
        $this->postJson("/api/v1/admin/users/{$user->id}/super-likes", ['delta' => -50])->assertOk()->assertJsonPath('data.super_likes_balance', 0);
        $this->postJson("/api/v1/admin/users/{$user->id}/super-likes", ['delta' => 0])->assertUnprocessable();

        $this->postJson("/api/v1/admin/users/{$user->id}/membership", ['plan' => 'yearly', 'days' => 30])->assertCreated()->assertJsonPath('data.granted', true);
        $this->assertTrue(app(MembershipService::class)->hasAccess($user->fresh()));
        $this->deleteJson("/api/v1/admin/users/{$user->id}/membership")->assertNoContent();
        $this->assertFalse(app(MembershipService::class)->hasAccess($user->fresh()));
        $this->deleteJson("/api/v1/admin/users/{$user->id}/membership")->assertStatus(409);

        $this->getJson('/api/v1/admin/staff')->assertOk()->assertJsonCount(2, 'data');

        $this->deleteJson("/api/v1/admin/users/{$user->id}")->assertUnprocessable();
        $this->deleteJson("/api/v1/admin/users/{$user->id}", ['note' => 'Solicitud por correo'])->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('admin_actions', ['action' => 'user.deleted', 'subject_id' => $user->id]);
        $this->assertDatabaseHas('activity_events', ['name' => 'account.deleted']);
    }

    public function test_profile_edits_verification_photos_and_sessions(): void
    {
        $this->staff();
        $user = User::factory()->withPhoto()->create(['bio' => 'texto ofensivo']);
        $photo = $user->photos()->first();

        $this->patchJson("/api/v1/admin/users/{$user->id}", ['bio' => null, 'show_in_discover' => false, 'note' => 'Bio inapropiada'])->assertOk()->assertJsonPath('data.show_in_discover', false);
        $this->assertNull($user->fresh()->bio);
        $this->postJson("/api/v1/admin/users/{$user->id}/verification", ['verified' => true])->assertNoContent();
        $this->assertTrue($user->fresh()->isVerified());
        $this->deleteJson("/api/v1/admin/users/{$user->id}/photos/{$photo->id}")->assertNoContent();
        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
        $this->postJson("/api/v1/admin/users/{$user->id}/logout")->assertOk()->assertJsonStructure(['data' => ['sessions_closed']]);

        $this->assertSame(4, AdminAction::query()->where('subject_id', $user->id)->count());
    }

    public function test_notes_and_tags(): void
    {
        $moderator = $this->staff();
        $user = User::factory()->create();

        $note = $this->postJson("/api/v1/admin/users/{$user->id}/notes", ['body' => 'Llamó a soporte', 'pinned' => true])->assertCreated()->assertJsonPath('data.author.id', $moderator->id)->json('data');
        $this->assertStringNotContainsString('soporte', (string) \DB::table('user_notes')->value('body'));
        $this->patchJson("/api/v1/admin/notes/{$note['id']}", ['body' => 'Llamó a soporte dos veces'])->assertOk()->assertJsonPath('data.body', 'Llamó a soporte dos veces');
        $this->getJson("/api/v1/admin/users/{$user->id}")->assertOk()->assertJsonCount(1, 'data.notes');

        $this->staff();
        $this->deleteJson("/api/v1/admin/notes/{$note['id']}")->assertForbidden();
        $this->staff(UserRole::Admin);
        $this->deleteJson("/api/v1/admin/notes/{$note['id']}")->assertNoContent();

        $this->putJson("/api/v1/admin/users/{$user->id}/tags", ['tags' => ['VIP', 'beta tester', 'vip']])->assertOk()->assertJsonPath('data.tags', ['vip', 'beta tester']);
        $this->putJson("/api/v1/admin/users/{$user->id}/tags", ['tags' => ['vip']])->assertOk();
        $this->assertSame(['vip'], $user->tags()->pluck('tag')->all());
        $this->getJson('/api/v1/admin/tags')->assertOk()->assertJsonPath('data.0.tag', 'vip');
        $this->putJson("/api/v1/admin/users/{$user->id}/tags", ['tags' => ['<script>']])->assertUnprocessable();
    }

    public function test_messages_are_only_readable_with_a_report_or_as_admin_and_the_read_is_audited(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $connection = Connection::factory()->between($a, $b)->create();
        $connection->messages()->create(['sender_id' => $a->id, 'body' => 'Mensaje privado']);

        $this->staff();
        $this->getJson("/api/v1/admin/connections/{$connection->id}")->assertOk()->assertJsonPath('data.messages_count', 1)->assertJsonMissing(['body' => 'Mensaje privado']);
        $this->getJson("/api/v1/admin/connections/{$connection->id}/messages")->assertForbidden();

        $report = new Report(['reported_id' => $a->id, 'reason' => 'Comportamiento inapropiado']);
        $report->forceFill(['reporter_id' => $b->id])->save();
        $this->getJson("/api/v1/admin/connections/{$connection->id}/messages?reason=Reporte")->assertOk()->assertJsonPath('data.0.body', 'Mensaje privado');
        $this->assertDatabaseHas('admin_actions', ['action' => 'messages.viewed', 'subject_id' => $connection->id, 'note' => 'Reporte']);

        $this->postJson("/api/v1/admin/connections/{$connection->id}/end")->assertNoContent();
        $this->assertNotNull($connection->fresh()->ended_at);
        $this->getJson('/api/v1/admin/connections?active=0')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_reports_queue_can_be_filtered_and_resolved(): void
    {
        $reporter = User::factory()->create();
        $reported = User::factory()->create();
        $report = new Report(['reported_id' => $reported->id, 'reason' => 'Perfil falso o spam', 'details' => 'Fotos robadas']);
        $report->forceFill(['reporter_id' => $reporter->id])->save();
        $moderator = $this->staff();

        $this->getJson('/api/v1/admin/reports')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.details', 'Fotos robadas');
        $this->getJson('/api/v1/admin/reports?reason=Otra')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/admin/reports/{$report->id}", ['status' => 'resolved', 'resolution_note' => 'Cuenta suspendida'])->assertOk()->assertJsonPath('data.handled_by.id', $moderator->id);
        $this->getJson('/api/v1/admin/reports')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/admin/reports?status=all')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/admin/users/{$reported->id}/reports")->assertOk()->assertJsonCount(1, 'data.received');
        $this->assertDatabaseHas('admin_actions', ['action' => 'report.updated', 'subject_id' => $report->id]);
    }

    public function test_billing_events_and_settings_endpoints_respond(): void
    {
        $this->staff(UserRole::Admin);
        $user = User::factory()->create();
        $this->postJson("/api/v1/admin/users/{$user->id}/membership", ['plan' => 'monthly', 'days' => 30])->assertCreated();

        $this->getJson('/api/v1/admin/billing/overview')->assertOk()->assertJsonPath('data.members.monthly', 1)->assertJsonStructure(['data' => ['mrr', 'revenue', 'revenue_series', 'conversion']]);
        $this->getJson('/api/v1/admin/billing/subscriptions?granted=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user.id', $user->id);
        $this->getJson('/api/v1/admin/billing/purchases')->assertOk();
        $this->getJson('/api/v1/admin/events?category=system')->assertOk()->assertJsonPath('data.0.name', 'admin.user.membership_granted');
        $this->getJson('/api/v1/admin/events/names')->assertOk();
        $this->getJson('/api/v1/admin/events/performance')->assertOk()->assertJsonStructure(['data' => ['routes']]);
        $this->getJson('/api/v1/admin/datecitos')->assertOk();
        $this->getJson('/api/v1/admin/here/sessions')->assertOk();
        $this->getJson('/api/v1/admin/safe-sessions')->assertOk();
        $this->getJson('/api/v1/admin/verifications')->assertOk();
        $this->getJson('/api/v1/admin/settings')->assertOk()->assertJsonStructure(['data' => ['flags', 'pricing', 'limits', 'health']]);
    }
}
