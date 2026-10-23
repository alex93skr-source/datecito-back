<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Message;
use App\Models\User;
use App\Services\DatecitoService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChatAndDatecitoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Connection}
     */
    private function connectedPair(array $meAttributes = [], array $otherAttributes = []): array
    {
        $me = User::factory()->withPhoto()->create($meAttributes);
        $other = User::factory()->withPhoto()->create($otherAttributes);
        $connection = Connection::factory()->between($me, $other)->create();
        $this->signIn($me);

        return [$me, $other, $connection];
    }

    public function test_participants_can_chat_and_messages_are_encrypted_at_rest(): void
    {
        [, , $connection] = $this->connectedPair();

        $this->postJson("/api/v1/connections/{$connection->id}/messages", ['body' => 'Hola, ¿café el domingo?'])
            ->assertCreated()
            ->assertJsonPath('data.from_me', true)
            ->assertJsonMissingPath('data.read_at')
            ->assertJsonMissingPath('data.seen');

        $raw = DB::table('messages')->value('body');
        $this->assertStringNotContainsString('café', $raw);
        $this->assertSame('Hola, ¿café el domingo?', Message::query()->sole()->body);
    }

    public function test_outsiders_cannot_read_or_write_a_conversation(): void
    {
        [, , $connection] = $this->connectedPair();
        $this->signIn(User::factory()->create());

        $this->getJson("/api/v1/connections/{$connection->id}/messages")->assertNotFound();
        $this->postJson("/api/v1/connections/{$connection->id}/messages", ['body' => 'hola'])->assertNotFound();
    }

    public function test_ended_or_blocked_conversations_are_closed(): void
    {
        [$me, $other, $connection] = $this->connectedPair();

        $this->postJson('/api/v1/blocks', ['user_id' => $other->id])->assertNoContent();

        $this->postJson("/api/v1/connections/{$connection->id}/messages", ['body' => 'hola'])->assertNotFound();
        $this->assertNotNull($connection->fresh()->ended_at);
        $this->assertSame([], $this->getJson('/api/v1/connections')->json('data'));
    }

    public function test_i_write_first_is_enforced_by_the_server(): void
    {
        [$me, $other, $connection] = $this->connectedPair(otherAttributes: ['i_write_first' => true]);

        $this->postJson("/api/v1/connections/{$connection->id}/messages", ['body' => 'hola'])
            ->assertForbidden()->assertJsonPath('code', 'recipient_writes_first');

        $connection->messages()->create(['sender_id' => $other->id, 'body' => 'Hola :)']);
        $this->postJson("/api/v1/connections/{$connection->id}/messages", ['body' => 'Hola!'])->assertCreated();
    }

    public function test_only_verified_messages_is_enforced_by_the_server(): void
    {
        [, , $connection] = $this->connectedPair(otherAttributes: ['only_verified_messages' => true]);

        $this->postJson("/api/v1/connections/{$connection->id}/messages", ['body' => 'hola'])
            ->assertForbidden()->assertJsonPath('code', 'recipient_requires_verified');
    }

    public function test_only_the_invited_person_can_confirm_a_datecito(): void
    {
        [$me, $other, $connection] = $this->connectedPair();

        $at = CarbonImmutable::now('America/Mexico_City')->addDays(5)->setTime(18, 30);

        $id = $this->postJson("/api/v1/connections/{$connection->id}/datecitos", [
            'kind' => 'cafe', 'place' => 'Café Avellaneda', 'zone' => 'Coyoacán', 'scheduled_at' => $at->toIso8601String(),
        ])->assertCreated()
            ->assertJsonPath('data.when_label', DatecitoService::label($at))
            ->assertJsonPath('data.scheduled_on', $at->toDateString())
            ->json('data.id');

        $this->postJson("/api/v1/datecitos/{$id}/confirm")->assertForbidden()->assertJsonPath('code', 'cannot_confirm_own');

        $this->signIn($other);
        $this->postJson("/api/v1/datecitos/{$id}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
    }

    public function test_feedback_is_private_encrypted_and_sent_once(): void
    {
        [$me, , $connection] = $this->connectedPair();
        $datecito = $connection->datecitos()->create(['proposer_id' => $me->id, 'kind' => 'cafe', 'place' => 'X', 'zone' => 'Y', 'when_label' => 'Ayer']);
        $datecito->forceFill(['status' => 'confirmed'])->save();
        $answers = ['showed' => 'Sí', 'photos' => 'Sí', 'safe' => 'No del todo', 'again' => 'No'];

        $this->postJson("/api/v1/datecitos/{$datecito->id}/feedback", $answers)->assertNoContent();
        $this->postJson("/api/v1/datecitos/{$datecito->id}/feedback", $answers)->assertJsonPath('code', 'feedback_already_sent');

        $this->assertStringNotContainsString('No del todo', DB::table('datecito_feedback')->value('answers'));
    }

    public function test_outsiders_cannot_see_a_datecito(): void
    {
        [$me, , $connection] = $this->connectedPair();
        $datecito = $connection->datecitos()->create(['proposer_id' => $me->id, 'kind' => 'bar', 'place' => 'X', 'zone' => 'Y', 'when_label' => 'Hoy']);
        $this->signIn(User::factory()->create());

        $this->getJson("/api/v1/datecitos/{$datecito->id}")->assertNotFound();
    }
}
