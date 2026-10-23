<?php

namespace Tests\Feature;

use App\Models\ActivityEvent;
use App\Models\Connection;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requests_are_recorded_as_events_with_platform_and_last_seen(): void
    {
        $user = $this->signIn();

        $this->withHeaders(['X-Platform' => 'ios', 'X-App-Version' => '1.2.0'])->getJson('/api/v1/me')->assertOk();

        $event = ActivityEvent::query()->where('user_id', $user->id)->sole();
        $this->assertSame('me.show', $event->name);
        $this->assertSame(ActivityEvent::CATEGORY_API, $event->category);
        $this->assertSame('ios', $event->platform);
        $this->assertSame('1.2.0', $event->app_version);
        $this->assertSame(200, $event->status);
        $this->assertNotNull($user->fresh()->last_seen_at);
        $this->assertSame('ios', $user->fresh()->last_platform);
    }

    public function test_only_whitelisted_properties_are_stored_never_message_bodies(): void
    {
        $me = User::factory()->withPhoto()->create();
        $other = User::factory()->withPhoto()->create();
        $connection = Connection::factory()->between($me, $other)->create();
        $this->signIn($me);

        $this->postJson("/api/v1/connections/{$connection->id}/messages", ['body' => 'secreto'])->assertCreated();
        $this->postJson('/api/v1/swipes', ['user_id' => User::factory()->create()->id, 'type' => 'like'])->assertOk();

        $message = ActivityEvent::query()->where('name', 'messages.store')->sole();
        $this->assertSame(['method' => 'POST'], $message->properties);
        $this->assertStringNotContainsString('secreto', json_encode($message->getAttributes()));

        $swipe = ActivityEvent::query()->where('name', 'swipes.store')->sole();
        $this->assertSame('like', $swipe->properties['type']);
        $this->assertArrayNotHasKey('user_id', $swipe->properties);
    }

    public function test_the_app_can_send_client_events_in_batches(): void
    {
        $user = $this->signIn();

        $this->withHeaders(['X-Platform' => 'android'])->postJson('/api/v1/events', ['events' => [
            ['name' => 'screen.discover', 'properties' => ['from' => 'tab']],
            ['name' => 'paywall.view', 'occurred_at' => now()->subMinute()->toIso8601String()],
        ]])->assertStatus(202);

        $this->assertSame(2, ActivityEvent::query()->where('user_id', $user->id)->where('category', ActivityEvent::CATEGORY_CLIENT)->count());
        $this->assertSame('android', ActivityEvent::query()->where('name', 'paywall.view')->sole()->platform);
        $this->assertDatabaseMissing('activity_events', ['name' => 'events.store']);
    }

    public function test_client_events_are_validated(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/events', ['events' => [['name' => 'DROP TABLE']]])->assertUnprocessable()->assertJsonValidationErrors('events.0.name');
        $this->postJson('/api/v1/events', ['events' => []])->assertUnprocessable();
    }

    public function test_matches_and_account_deletions_leave_system_events(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $other->swipes()->create(['target_id' => $me->id, 'type' => 'like']);
        $this->signIn($me);

        $this->postJson('/api/v1/swipes', ['user_id' => $other->id, 'type' => 'like'])->assertOk()->assertJsonPath('matched', true);
        $this->assertSame(2, ActivityEvent::query()->where('name', 'connection.created')->count());

        app(AccountService::class)->delete($other);
        $deleted = ActivityEvent::query()->where('name', 'account.deleted')->sole();
        $this->assertNull($deleted->user_id);
        $this->assertSame('self', $deleted->properties['reason']);
    }
}
