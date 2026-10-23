<?php

namespace Tests\Feature;

use App\Enums\SwipeType;
use App\Models\Block;
use App\Models\Connection;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoveryAndMatchingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function deckIds(): array
    {
        return collect($this->getJson('/api/v1/discover')->assertOk()->json('data'))->pluck('id')->all();
    }

    public function test_deck_only_shows_people_who_can_be_met(): void
    {
        $viewer = $this->signIn(User::factory()->withPhoto()->create());
        $visible = User::factory()->withPhoto()->create();
        $swiped = User::factory()->withPhoto()->create();
        $blockedMe = User::factory()->withPhoto()->create();
        $connected = User::factory()->withPhoto()->create();
        $hidden = User::factory()->withPhoto()->create(['show_in_discover' => false]);
        $unverified = User::factory()->withPhoto()->unverified()->create();
        $withoutPhotos = User::factory()->create();

        $viewer->swipes()->create(['target_id' => $swiped->id, 'type' => SwipeType::Pass]);
        (new Block(['blocked_id' => $viewer->id]))->forceFill(['blocker_id' => $blockedMe->id])->save();
        Connection::factory()->between($viewer, $connected)->create();

        $ids = $this->deckIds();

        $this->assertContains($visible->id, $ids);
        foreach ([$viewer, $swiped, $blockedMe, $connected, $hidden, $unverified, $withoutPhotos] as $excluded) {
            $this->assertNotContains($excluded->id, $ids);
        }
    }

    public function test_deck_never_exposes_private_fields(): void
    {
        $this->signIn(User::factory()->withPhoto()->create());
        User::factory()->withPhoto()->locatedAt()->create();

        $person = $this->getJson('/api/v1/discover')->json('data.0');

        foreach (['email', 'birthdate', 'approx_lat', 'approx_lng', 'status', 'role', 'filters', 'password'] as $field) {
            $this->assertArrayNotHasKey($field, $person);
        }
    }

    public function test_intent_filters_while_interests_only_reorder(): void
    {
        $this->signIn(User::factory()->withPhoto()->create(['interests' => []]));
        $hiker = User::factory()->withPhoto()->create(['interests' => ['Senderismo'], 'intent' => 'Algo serio, con calma']);
        $other = User::factory()->withPhoto()->create(['interests' => ['Cine'], 'intent' => 'Conocer sin presión']);
        $filters = ['meet' => 'Todas las personas', 'minAge' => 18, 'maxAge' => 60, 'km' => 25, 'intents' => [], 'relationships' => [], 'genres' => [], 'lifestyle' => [], 'languages' => [], 'verifiedOnly' => false];

        // Un interés (aunque venga en modo "only" de una versión anterior) no oculta a nadie: sólo ordena.
        $this->putJson('/api/v1/me/filters', [...$filters, 'interests' => ['Senderismo'], 'modes' => ['interests' => 'only']])->assertOk();
        $this->assertSame([$hiker->id, $other->id], $this->deckIds());

        $this->putJson('/api/v1/me/filters', [...$filters, 'interests' => [], 'intents' => ['Conocer sin presión'], 'modes' => []])->assertOk();
        $this->assertSame([$other->id], $this->deckIds());
    }

    public function test_deck_respects_who_each_person_wants_to_meet(): void
    {
        $this->signIn(User::factory()->withPhoto()->create(['gender' => 'Hombre', 'interested_in' => ['Mujeres']]));
        $wantsMen = User::factory()->withPhoto()->create(['gender' => 'Mujer', 'interested_in' => ['Hombres']]);
        $wantsWomen = User::factory()->withPhoto()->create(['gender' => 'Mujer', 'interested_in' => ['Mujeres']]);

        $this->putJson('/api/v1/me/filters', ['meet' => 'Mujeres', 'minAge' => 18, 'maxAge' => 60, 'km' => 25, 'intents' => [], 'relationships' => [], 'interests' => [], 'genres' => [], 'lifestyle' => [], 'languages' => [], 'verifiedOnly' => false, 'modes' => []]);

        $this->assertSame([$wantsMen->id], $this->deckIds());
        $this->assertNotContains($wantsWomen->id, $this->deckIds());
    }

    public function test_mutual_like_creates_a_connection(): void
    {
        $other = User::factory()->withPhoto()->create();
        $viewer = $this->signIn(User::factory()->withPhoto()->create());
        $other->swipes()->create(['target_id' => $viewer->id, 'type' => SwipeType::Like]);

        $response = $this->postJson('/api/v1/swipes', ['user_id' => $other->id, 'type' => 'like']);

        $response->assertOk()->assertJsonPath('matched', true);
        $this->assertNotNull(Connection::between($viewer, $other));
    }

    public function test_a_one_sided_like_does_not_reveal_anything(): void
    {
        $other = User::factory()->withPhoto()->create();
        $this->signIn(User::factory()->withPhoto()->create());

        $this->postJson('/api/v1/swipes', ['user_id' => $other->id, 'type' => 'like'])
            ->assertOk()->assertJsonPath('matched', false)->assertJsonPath('connection_id', null);
    }

    public function test_free_super_like_comes_back_every_few_days(): void
    {
        $this->signIn(User::factory()->withPhoto()->create());
        [$first, $second] = User::factory()->count(2)->withPhoto()->create();

        $this->postJson('/api/v1/swipes', ['user_id' => $first->id, 'type' => 'super'])->assertOk()->assertJsonPath('super_likes.free_today', 0);

        $this->travel(2)->days();
        $this->postJson('/api/v1/swipes', ['user_id' => $second->id, 'type' => 'super'])->assertStatus(402);

        $this->travel(1)->days();
        $this->postJson('/api/v1/swipes', ['user_id' => $second->id, 'type' => 'super'])->assertOk();
    }

    public function test_likes_are_limited_per_day_but_passes_are_not(): void
    {
        config(['datecito.limits.likes_per_day' => 2]);
        $this->signIn(User::factory()->withPhoto()->create());
        $people = User::factory()->count(4)->withPhoto()->create();

        $this->postJson('/api/v1/swipes', ['user_id' => $people[0]->id, 'type' => 'like'])->assertOk()->assertJsonPath('likes_left', 1);
        $this->postJson('/api/v1/swipes', ['user_id' => $people[1]->id, 'type' => 'like'])->assertOk()->assertJsonPath('likes_left', 0);
        $this->postJson('/api/v1/swipes', ['user_id' => $people[2]->id, 'type' => 'like'])->assertForbidden()->assertJsonPath('code', 'no_likes_left');
        $this->postJson('/api/v1/swipes', ['user_id' => $people[3]->id, 'type' => 'pass'])->assertOk();
    }

    public function test_deck_stops_at_the_daily_profiles_and_explains_why(): void
    {
        config(['datecito.limits.profiles_per_day' => 2]);
        $this->signIn(User::factory()->withPhoto()->create());
        $people = User::factory()->count(3)->withPhoto()->create();

        $this->getJson('/api/v1/discover')->assertJsonCount(2, 'data')->assertJsonPath('meta.profiles_left', 2);

        $this->postJson('/api/v1/swipes', ['user_id' => $people[0]->id, 'type' => 'pass'])->assertOk();
        $this->postJson('/api/v1/swipes', ['user_id' => $people[1]->id, 'type' => 'pass'])->assertOk();

        $this->getJson('/api/v1/discover')->assertJsonCount(0, 'data')->assertJsonPath('meta.empty_reason', 'daily_limit');
    }

    public function test_passed_people_come_back_after_a_while(): void
    {
        $this->signIn(User::factory()->withPhoto()->create());
        $other = User::factory()->withPhoto()->create();

        $this->postJson('/api/v1/swipes', ['user_id' => $other->id, 'type' => 'pass'])->assertOk();
        $this->assertSame([], $this->deckIds());

        $this->travel(15)->days();
        $this->assertSame([$other->id], $this->deckIds());
        $this->postJson('/api/v1/swipes', ['user_id' => $other->id, 'type' => 'like'])->assertOk();
        $this->assertSame('like', Swipe::query()->where('target_id', $other->id)->value('type')->value);
    }

    public function test_super_likes_received_show_on_the_card_and_in_activity(): void
    {
        $me = $this->signIn(User::factory()->withPhoto()->create());
        $fan = User::factory()->withPhoto()->create(['name' => 'Mar']);
        $fan->swipes()->create(['target_id' => $me->id, 'type' => 'super']);

        $this->getJson('/api/v1/discover')->assertJsonPath('data.0.id', $fan->id)->assertJsonPath('data.0.super_liked_me', true);
        $this->getJson('/api/v1/activity')->assertJsonPath('data.0.type', 'super_like')->assertJsonPath('data.0.person_id', $fan->id);
    }

    public function test_people_who_liked_me_are_listed_and_liking_back_matches_without_spending_the_daily_limit(): void
    {
        config(['datecito.limits.likes_per_day' => 0]);
        $me = $this->signIn(User::factory()->withPhoto()->create());
        $fan = User::factory()->withPhoto()->create();
        $fan->swipes()->create(['target_id' => $me->id, 'type' => 'like']);

        $this->getJson('/api/v1/likes')->assertOk()
            ->assertJsonPath('meta.count', 1)->assertJsonPath('meta.locked', false)
            ->assertJsonPath('data.0.id', $fan->id)->assertJsonPath('data.0.liked_me', true);

        $this->postJson('/api/v1/swipes', ['user_id' => $fan->id, 'type' => 'like'])->assertOk()->assertJsonPath('matched', true);
        $this->getJson('/api/v1/likes')->assertJsonPath('meta.count', 0);
    }

    public function test_without_membership_likes_received_are_only_counted(): void
    {
        config(['datecito.launch_free' => false]);
        $me = $this->signIn(User::factory()->withPhoto()->create());
        $fan = User::factory()->withPhoto()->create(['name' => 'Mar']);
        $fan->swipes()->create(['target_id' => $me->id, 'type' => 'like']);

        $this->getJson('/api/v1/likes')->assertOk()
            ->assertJsonPath('meta.count', 1)->assertJsonPath('meta.locked', true)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.teasers.0.initial', 'M')
            ->assertJsonMissing(['id' => $fan->id]);
    }

    public function test_people_in_verification_are_flagged_as_pending(): void
    {
        $this->signIn(User::factory()->withPhoto()->create());
        $pending = User::factory()->withPhoto()->create(['verification_status' => 'pending']);

        $this->getJson("/api/v1/people/{$pending->id}")->assertOk()
            ->assertJsonPath('data.verification_pending', true)
            ->assertJsonPath('data.verified', false);
    }

    public function test_one_free_super_like_per_day_then_purchased_ones(): void
    {
        $viewer = $this->signIn(User::factory()->withPhoto()->create());
        [$first, $second, $third] = User::factory()->count(3)->withPhoto()->create();

        $this->postJson('/api/v1/swipes', ['user_id' => $first->id, 'type' => 'super'])->assertOk()->assertJsonPath('super_likes.free_today', 0);
        $this->postJson('/api/v1/swipes', ['user_id' => $second->id, 'type' => 'super'])->assertStatus(402)->assertJsonPath('code', 'no_super_likes');
        $this->assertFalse(Swipe::query()->where('target_id', $second->id)->exists());

        $this->postJson('/api/v1/membership/purchases', ['store' => 'fake', 'product_id' => 'datecito_superlikes_10', 'purchase_token' => 'tok-1'])->assertCreated();
        $this->postJson('/api/v1/membership/purchases', ['store' => 'fake', 'product_id' => 'datecito_superlikes_10', 'purchase_token' => 'tok-1'])->assertCreated();
        $this->assertSame(10, $viewer->fresh()->super_likes_balance);

        $this->postJson('/api/v1/swipes', ['user_id' => $third->id, 'type' => 'super'])->assertOk()->assertJsonPath('super_likes.purchased', 9);
    }

    public function test_people_with_a_block_cannot_be_swiped_or_viewed(): void
    {
        $other = User::factory()->withPhoto()->create();
        $viewer = $this->signIn(User::factory()->withPhoto()->create());
        $viewer->blocksMade()->create(['blocked_id' => $other->id]);

        $this->postJson('/api/v1/swipes', ['user_id' => $other->id, 'type' => 'like'])->assertNotFound();
        $this->getJson('/api/v1/people/'.$other->id)->assertNotFound();
    }
}
