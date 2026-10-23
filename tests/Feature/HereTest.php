<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\User;
use App\Services\HereService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HereTest extends TestCase
{
    use RefreshDatabase;

    private function activate(User $user, float $lat, float $lng, bool $verifiedOnly = false): void
    {
        app(HereService::class)->start($user, 'Café Avellaneda', $lat, $lng, 60, $verifiedOnly);
    }

    /**
     * @return list<string>
     */
    private function peopleIds(): array
    {
        return collect($this->getJson('/api/v1/here/people')->assertOk()->json('data'))->pluck('person.id')->all();
    }

    public function test_only_people_in_the_same_place_are_visible_without_coordinates(): void
    {
        $viewer = User::factory()->create();
        $near = User::factory()->withPhoto()->create();
        $far = User::factory()->withPhoto()->create();
        $this->activate($viewer, 19.3500, -99.1620);
        $this->activate($near, 19.3504, -99.1622);
        $this->activate($far, 19.4300, -99.1300);
        $this->signIn($viewer);

        $response = $this->getJson('/api/v1/here/people')->assertOk();

        $this->assertSame([$near->id], collect($response->json('data'))->pluck('person.id')->all());
        $this->assertStringNotContainsString('19.35', $response->getContent());
        $this->getJson('/api/v1/here')->assertJsonMissingPath('data.lat');
    }

    public function test_verified_only_sessions_are_hidden_from_unverified_people(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->withPhoto()->create();
        $this->activate($viewer, 19.35, -99.162);
        $this->activate($other, 19.35, -99.162, verifiedOnly: true);
        $this->signIn($viewer);

        $this->assertSame([], $this->peopleIds());
    }

    public function test_hiding_works_in_both_directions(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->withPhoto()->create();
        $this->activate($viewer, 19.35, -99.162);
        $this->activate($other, 19.35, -99.162);
        $this->signIn($other);
        $this->postJson('/api/v1/here/hidden', ['user_id' => $viewer->id])->assertNoContent();

        $this->signIn($viewer);
        $this->assertSame([], $this->peopleIds());
    }

    public function test_mutual_greeting_creates_a_connection(): void
    {
        $a = User::factory()->withPhoto()->create();
        $b = User::factory()->withPhoto()->create();
        $this->activate($a, 19.35, -99.162);
        $this->activate($b, 19.35, -99.162);

        $this->signIn($a);
        $this->postJson('/api/v1/here/greets', ['user_id' => $b->id])->assertOk()->assertJsonPath('matched', false);

        $this->signIn($b);
        $this->postJson('/api/v1/here/greets', ['user_id' => $a->id])->assertOk()->assertJsonPath('matched', true);
        $this->assertSame('here', Connection::between($a, $b)->source->value);
    }

    public function test_cannot_greet_someone_who_is_not_there(): void
    {
        $a = User::factory()->create();
        $this->activate($a, 19.35, -99.162);
        $this->signIn($a);

        $this->postJson('/api/v1/here/greets', ['user_id' => User::factory()->create()->id])->assertNotFound();
    }
}
