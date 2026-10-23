<?php

namespace App\Services;

use App\Enums\EmailCodePurpose;
use App\Mail\EmailCodeMail;
use App\Models\EmailCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Códigos de 6 dígitos para verificar correo y recuperar contraseña.
 *
 * Seguridad: sólo se guarda un HMAC del código, expiran en minutos, tienen un
 * máximo de intentos y cada código nuevo invalida los anteriores.
 */
class EmailCodeService
{
    public function send(string $email, EmailCodePurpose $purpose): void
    {
        $email = Str::lower($email);
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($email, $purpose, $code): void {
            EmailCode::query()
                ->where('email', $email)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            EmailCode::create([
                'email' => $email,
                'purpose' => $purpose,
                'code_hash' => $this->hash($email, $purpose, $code),
                'expires_at' => now()->addMinutes(config('datecito.limits.email_code_ttl_minutes')),
            ]);
        });

        Mail::to($email)->queue(new EmailCodeMail($code, $purpose));
    }

    /**
     * Envía un código nuevo salvo que ya se haya enviado uno hace muy poco
     * (evita llenar el correo de códigos y el abuso del reenvío).
     */
    public function sendUnlessRecent(string $email, EmailCodePurpose $purpose, int $seconds = 60): bool
    {
        $recent = EmailCode::query()
            ->where('email', Str::lower($email))
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('created_at', '>', now()->subSeconds($seconds))
            ->exists();

        if ($recent) {
            return false;
        }

        $this->send($email, $purpose);

        return true;
    }

    /**
     * Consume el código si es válido. Cuenta los intentos fallidos.
     */
    public function consume(string $email, EmailCodePurpose $purpose, string $code): bool
    {
        $email = Str::lower($email);

        return DB::transaction(function () use ($email, $purpose, $code): bool {
            $record = EmailCode::query()
                ->where('email', $email)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($record === null || $record->attempts >= config('datecito.limits.email_code_max_attempts')) {
                return false;
            }

            if (! hash_equals($record->code_hash, $this->hash($email, $purpose, $code))) {
                $record->increment('attempts');

                return false;
            }

            $record->forceFill(['consumed_at' => now()])->save();

            return true;
        });
    }

    private function hash(string $email, EmailCodePurpose $purpose, string $code): string
    {
        return hash_hmac('sha256', $email.'|'.$purpose->value.'|'.$code, (string) config('app.key'));
    }
}
