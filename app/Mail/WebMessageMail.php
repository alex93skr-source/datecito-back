<?php

namespace App\Mail;

use App\Models\WebMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso al equipo de un mensaje recibido por el sitio web.
 */
class WebMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    private const SUBJECTS = [
        'contact' => 'Nuevo mensaje de contacto',
        'partners' => 'Nueva propuesta de alianza',
        'arco' => 'Nueva solicitud de derechos ARCO',
        'campus' => 'Nuevo registro en lista de espera',
        'newsletter' => 'Nueva suscripción al boletín',
    ];

    public function __construct(public WebMessage $message) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: (self::SUBJECTS[$this->message->kind] ?? 'Nuevo mensaje del sitio').' · '.$this->message->folio(),
            replyTo: [new Address($this->message->email, $this->message->name ?? $this->message->email)],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.web-message', with: ['m' => $this->message]);
    }
}
