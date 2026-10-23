<?php

namespace App\Mail;

use App\Enums\EmailCodePurpose;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo con un código de un solo uso.
 */
class EmailCodeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public EmailCodePurpose $purpose,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->purpose === EmailCodePurpose::VerifyEmail
                ? 'Tu código para Datecito'
                : 'Recupera tu acceso a Datecito',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.email-code',
            with: [
                'code' => $this->code,
                'minutes' => config('datecito.limits.email_code_ttl_minutes'),
                'isReset' => $this->purpose === EmailCodePurpose::ResetPassword,
            ],
        );
    }
}
