<?php

namespace Tests\Feature;

use App\Enums\SafeSessionStatus;
use App\Models\Connection;
use App\Models\Report;
use App\Models\SafeSession;
use App\Models\User;
use App\Services\SafeSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_is_confidential_encrypted_and_can_also_block(): void
    {
        $reported = User::factory()->create();
        $reporter = $this->signIn();

        $this->postJson('/api/v1/reports', [
            'user_id' => $reported->id,
            'reason' => 'Me preocupa mi seguridad',
            'details' => 'Me pidió mi dirección exacta.',
            'also_block' => true,
        ])->assertCreated()->assertJsonMissingPath('data');

        $this->assertStringNotContainsString('dirección', DB::table('reports')->value('details'));
        $this->assertSame('Me pidió mi dirección exacta.', Report::query()->sole()->details);
        $this->assertTrue($reporter->hasBlockWith($reported));
    }

    public function test_trusted_contacts_are_encrypted_and_limited(): void
    {
        $this->signIn();

        foreach (['Fer', 'Mamá', 'Luis'] as $name) {
            $this->postJson('/api/v1/trusted-contacts', ['name' => $name, 'phone' => '55 1234 5678'])->assertCreated();
        }

        $this->postJson('/api/v1/trusted-contacts', ['name' => 'Otra', 'phone' => '55 1234 5678'])->assertJsonPath('code', 'contacts_limit');
        $this->assertStringNotContainsString('5678', DB::table('trusted_contacts')->value('phone'));
    }

    public function test_another_persons_contact_cannot_be_deleted(): void
    {
        $contact = User::factory()->create()->trustedContacts()->create(['name' => 'Fer', 'phone' => '5512345678']);
        $this->signIn();

        $this->deleteJson('/api/v1/trusted-contacts/'.$contact->id)->assertNotFound();
        $this->assertModelExists($contact);
    }

    public function test_safe_session_only_accepts_own_contacts_and_returns_a_share_message(): void
    {
        $me = User::factory()->create(['name' => 'Alex']);
        $other = User::factory()->create(['name' => 'Mar']);
        $connection = Connection::factory()->between($me, $other)->create();
        $datecito = $connection->datecitos()->create(['proposer_id' => $me->id, 'kind' => 'cafe', 'place' => 'Café Avellaneda', 'zone' => 'Coyoacán', 'when_label' => 'Domingo']);
        $mine = $me->trustedContacts()->create(['name' => 'Fer', 'phone' => '5512345678']);
        $foreign = $other->trustedContacts()->create(['name' => 'X', 'phone' => '5512345678']);
        $this->signIn($me);
        $payload = ['datecito_id' => $datecito->id, 'check_in_minutes' => 30, 'share_location' => true];

        $this->postJson('/api/v1/safe-sessions', [...$payload, 'contact_ids' => [$foreign->id]])->assertJsonPath('code', 'contacts_invalid');

        $id = $this->postJson('/api/v1/safe-sessions', [...$payload, 'contact_ids' => [$mine->id]])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.share_message', fn (string $text) => str_contains($text, 'Alex tiene un datecito con Mar en Café Avellaneda'))
            ->json('data.id');

        // "Estoy bien" vuelve a preguntar con el mismo intervalo elegido (30 min), no con uno fijo.
        $this->travel(25)->minutes();
        $this->postJson("/api/v1/safe-sessions/{$id}/check-in")->assertOk()
            ->assertJsonPath('data.check_in_at', now()->addMinutes(30)->toIso8601String());
    }

    public function test_sessions_without_check_in_become_overdue(): void
    {
        $me = User::factory()->create();
        $connection = Connection::factory()->between($me, User::factory()->create())->create();
        $datecito = $connection->datecitos()->create(['proposer_id' => $me->id, 'kind' => 'cafe', 'place' => 'X', 'zone' => 'Y', 'when_label' => 'Hoy']);
        $session = new SafeSession(['datecito_id' => $datecito->id, 'contact_ids' => [], 'check_in_at' => now()->subMinutes(30), 'share_location' => false]);
        $session->forceFill(['user_id' => $me->id, 'status' => SafeSessionStatus::Active])->save();

        app(SafeSessionService::class)->markOverdue();

        $this->assertSame(SafeSessionStatus::Overdue, $session->fresh()->status);
    }

    public function test_safety_tools_work_without_membership(): void
    {
        config(['datecito.launch_free' => false]);
        $this->signIn();

        $this->getJson('/api/v1/discover')->assertStatus(402)->assertJsonPath('code', 'membership_required');
        $this->postJson('/api/v1/trusted-contacts', ['name' => 'Fer', 'phone' => '5512345678'])->assertCreated();
        $this->postJson('/api/v1/reports', ['reason' => 'Perfil falso o spam'])->assertCreated();
    }
}
