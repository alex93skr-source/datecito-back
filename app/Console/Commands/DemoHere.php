<?php

namespace App\Console\Commands;

use App\Models\HereSession;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * SOLO desarrollo: coloca a las personas demo en el mismo lugar donde la cuenta
 * indicada activó "Aquí y ahora", para poder probar la función con un solo celular.
 */
#[Signature('datecito:demo-here {email=demo@datecito.test}')]
#[Description('Coloca personas demo junto a la sesión de Aquí y ahora de una cuenta (solo local)')]
class DemoHere extends Command
{
    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->components->error('Este comando sólo funciona en local.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $this->argument('email'))->first();
        $session = $user === null ? null : HereSession::query()->where('user_id', $user->id)->live()->latest()->first();

        if ($session === null) {
            $this->components->error('Primero activa Aquí y ahora en la app con esa cuenta.');

            return self::FAILURE;
        }

        foreach (['andres', 'sofia', 'camila'] as $key) {
            $person = User::query()->where('email', $key.'@datecito.test')->first();

            if ($person === null) {
                continue;
            }

            HereSession::query()->where('user_id', $person->id)->delete();
            (new HereSession([
                'place' => $session->place,
                'lat' => $session->lat,
                'lng' => $session->lng,
                'verified_only' => false,
                'ends_at' => $session->ends_at,
            ]))->forceFill(['user_id' => $person->id])->save();
        }

        $this->components->info("Personas demo colocadas en {$session->place}.");

        return self::SUCCESS;
    }
}
