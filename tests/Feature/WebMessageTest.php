<?php

namespace Tests\Feature;

use App\Mail\WebMessageMail;
use App\Models\User;
use App\Models\WebMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_is_stored_encrypted_and_notifies_support(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/web/messages', [
            'kind' => 'contact', 'name' => 'Ana López', 'email' => 'Ana@Correo.com', 'topic' => 'Mi cuenta',
            'message' => 'No puedo verificar mi correo desde ayer.', 'accepted' => true, 'lang' => 'es',
        ])->assertCreated()->assertJsonStructure(['folio']);

        $message = WebMessage::query()->sole();
        $this->assertSame('ana@correo.com', $message->email);
        $this->assertSame('No puedo verificar mi correo desde ayer.', $message->message);
        $this->assertNotSame($message->message, $message->getRawOriginal('message'));
        Mail::assertQueued(WebMessageMail::class, fn (WebMessageMail $mail) => $mail->hasTo(config('datecito.web.notify.contact')));
    }

    public function test_bots_filling_the_hidden_field_are_rejected(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/web/messages', [
            'kind' => 'newsletter', 'email' => 'bot@spam.com', 'accepted' => true, 'website' => 'http://spam',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('web_messages', 0);
        Mail::assertNothingQueued();
    }

    public function test_arco_request_accepts_an_id_attachment_and_moderators_can_manage_it(): void
    {
        Mail::fake();
        Storage::fake('s3');
        Storage::fake('s3_public');

        $folio = $this->postJson('/api/v1/web/messages', [
            'kind' => 'arco', 'name' => 'Luis Pérez', 'email' => 'luis@correo.com', 'topic' => 'Cancelación',
            'message' => 'Quiero que borren todos mis datos personales.', 'accepted' => true,
            'attachment' => UploadedFile::fake()->image('ine.jpg', 800, 500),
        ])->assertCreated()->json('folio');

        $this->assertStringStartsWith('ARCO-', $folio);
        $message = WebMessage::query()->sole();
        Storage::disk('s3')->assertExists($message->attachment_key);

        $this->signIn(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/v1/admin/web-messages?kind=arco')->assertOk()->assertJsonPath('data.0.has_attachment', true);
        $this->getJson("/api/v1/admin/web-messages/{$message->id}/attachment")->assertOk()->assertJsonStructure(['url']);
        $this->patchJson("/api/v1/admin/web-messages/{$message->id}", ['status' => 'answered'])->assertOk()->assertJsonPath('data.status', 'answered');
    }

    public function test_regular_users_cannot_read_web_messages(): void
    {
        $this->signIn();

        $this->getJson('/api/v1/admin/web-messages')->assertForbidden();
    }
}
