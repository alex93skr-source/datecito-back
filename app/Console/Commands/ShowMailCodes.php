<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Mientras el correo sale por el canal "log" (sin SMTP), muestra los últimos códigos de 6 dígitos
 * enviados a una dirección, leyendo storage/logs/mail-*.log. Pensado para pruebas, no para producción con SMTP.
 */
#[Signature('datecito:mail-codes {email : Correo de la persona} {--limit=3 : Cuántos mostrar}')]
#[Description('Muestra los últimos códigos enviados a un correo cuando MAIL_MAILER=log')]
class ShowMailCodes extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $files = collect(File::glob(storage_path('logs/mail-*.log')))->sortDesc()->take(2);
        $found = [];

        foreach ($files as $file) {
            $content = File::get($file);
            // Cada correo del canal log empieza con sus cabeceras; separamos por "From:" para aislar mensajes.
            foreach (preg_split('/(?=^From: )/m', $content) ?: [] as $chunk) {
                if (! str_contains(mb_strtolower($chunk), 'to: '.$email)) {
                    continue;
                }
                if (preg_match('/Date: (.+)/', $chunk, $date) && preg_match_all('/\b(\d{6})\b/', $chunk, $codes)) {
                    $found[] = [trim($date[1]), $codes[1][0]];
                }
            }
        }

        if ($found === []) {
            $this->components->warn("No hay correos para $email en los registros recientes.");

            return self::SUCCESS;
        }

        $this->table(['Fecha', 'Código'], array_slice(array_reverse($found), 0, (int) $this->option('limit')));

        return self::SUCCESS;
    }
}
