<?php

namespace App\Console\Commands;

use App\Enums\ConnectionSource;
use App\Enums\SwipeType;
use App\Models\Connection;
use App\Models\User;
use App\Services\ConnectionService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SOLO desarrollo: le da matches y conversaciones a una cuenta real para probar cómo
 * se comporta la app con mucho movimiento. Las otras personas son siempre de prueba.
 */
#[Signature('datecito:fake-matches
    {email : Cuenta que recibe los matches}
    {--matches=50 : Cuántos matches crear}
    {--chats=10 : Cuántos de ellos tendrán conversación larga}')]
#[Description('Crea matches y conversaciones de prueba para una cuenta (solo local)')]
class FakeMatches extends Command
{
    /**
     * Guion de conversación: [quién habla (them|me), texto]. {interest} y {name} se reemplazan.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const SCRIPT = [
        ['them', '¡Hola! Me dio gusto el match 😊'],
        ['me', 'Hola {name}, igualmente. ¿Cómo va tu día?'],
        ['them', 'Tranqui, saliendo del trabajo. ¿Y el tuyo?'],
        ['me', 'Bien, con ganas de que ya sea viernes jaja'],
        ['them', 'Jajaja somos dos'],
        ['me', 'Vi que te gusta {interest}, ¿desde cuándo?'],
        ['them', 'Uff, desde hace años. Es de las cosas que más disfruto'],
        ['them', '¿A ti también te late?'],
        ['me', 'Sí, aunque no tanto como quisiera, el tiempo no alcanza'],
        ['them', 'Te entiendo totalmente'],
        ['them', 'Oye, ¿y a qué te dedicas?'],
        ['me', 'Trabajo en ventas, mucho teléfono y mucha paciencia jaja'],
        ['them', 'Jaja me imagino. Yo no tendría tanta paciencia'],
        ['me', '¿Y tú?'],
        ['them', 'Estoy en una agencia de diseño, me encanta pero a veces es intenso'],
        ['me', 'Suena padre, ¿qué tipo de proyectos haces?'],
        ['them', 'Un poco de todo: marcas, redes, a veces empaques'],
        ['them', 'El último fue para una cafetería en la Roma ☕'],
        ['me', '¡Qué buena onda! ¿Y el café está bueno?'],
        ['them', 'Buenísimo, te lo recomiendo'],
        ['me', 'Va, lo anoto'],
        ['them', '¿Eres más de planes tranquilos o de salir?'],
        ['me', 'Depende, pero un buen café y platicar me gana'],
        ['them', 'Igual yo. Los antros ya no tanto jaja'],
        ['me', 'Jajaja ya estamos grandes'],
        ['them', 'Habla por ti 😂'],
        ['me', 'Jajaja ok ok'],
        ['them', '¿Qué haces los domingos normalmente?'],
        ['me', 'Mercado, chilaquiles y a veces una película'],
        ['them', 'Eso suena a domingo perfecto'],
        ['them', '¿Qué película viste la última vez?'],
        ['me', 'Una de terror que no me dejó dormir jaja'],
        ['them', 'Noooo, yo no puedo con las de terror'],
        ['me', 'Entonces nada de terror para la primera cita 😅'],
        ['them', 'Jaja trato hecho'],
        ['them', 'Oye, ¿y de dónde eres?'],
        ['me', 'De aquí del Estado de México, ¿tú?'],
        ['them', 'Yo nací en Puebla pero llevo años aquí'],
        ['me', '¡Puebla! Me debes unas cemitas entonces'],
        ['them', 'Jajaja eso se puede arreglar'],
        ['me', '¿Te late un café esta semana?'],
        ['them', 'Me encantaría ✨'],
        ['them', '¿Qué día te queda?'],
        ['me', '¿El jueves en la tarde?'],
        ['them', 'Jueves perfecto. ¿Como a las 6?'],
        ['me', 'Va, 6 está bien'],
        ['them', 'Te propongo el lugar en un datecito para que quede'],
        ['me', 'Perfecto, ahí lo veo'],
        ['them', 'Qué gusto, nos vemos el jueves 🙌'],
        ['me', 'Nos vemos, que descanses'],
    ];

    /**
     * Arranques cortos para matches con poca conversación.
     *
     * @var list<string>
     */
    private const OPENERS = [
        'Hola, ¿qué tal tu semana?',
        '¡Hey! Me encantaron tus fotos de viaje',
        'Hola 👋 ¿Tacos o pizza?',
        'Me llamó la atención que te guste {interest}',
        '¿Cuál es tu plan ideal para un domingo?',
    ];

    public function handle(ConnectionService $connections): int
    {
        if (! app()->environment('local')) {
            $this->components->error('Este comando sólo funciona en local.');

            return self::FAILURE;
        }

        $me = User::query()->where('email', $this->argument('email'))->first();

        if ($me === null) {
            $this->components->error('No existe esa cuenta.');

            return self::FAILURE;
        }

        $count = (int) $this->option('matches');
        $chats = min($count, (int) $this->option('chats'));
        $people = $this->candidates($me)->take($count);

        if ($people->count() < $count) {
            $this->components->warn("Sólo hay {$people->count()} personas de prueba compatibles.");
        }

        $now = CarbonImmutable::now();

        $this->components->task("Creando {$people->count()} matches", function () use ($people, $me, $connections, $chats, $now): void {
            foreach ($people->values() as $index => $person) {
                // Los matches se reparten en las últimas dos semanas; los primeros son los más activos.
                $matchedAt = $now->subMinutes(random_int(30, 60 * 24 * 14));

                DB::transaction(function () use ($me, $person, $connections, $index, $chats, $matchedAt, $now): void {
                    foreach ([[$me, $person], [$person, $me]] as [$from, $to]) {
                        $from->swipes()->updateOrCreate(['target_id' => $to->id], ['type' => SwipeType::Like]);
                    }

                    $connection = $connections->open($me, $person, ConnectionSource::Discover);
                    $connection->forceFill(['created_at' => $matchedAt, 'updated_at' => $matchedAt])->save();

                    if ($index < $chats) {
                        $this->conversation($connection, $me, $person, $matchedAt, $now, random_int(18, count(self::SCRIPT)));
                    } elseif (random_int(1, 100) <= 40) {
                        $this->conversation($connection, $me, $person, $matchedAt, $now, random_int(1, 3), short: true);
                    }
                });
            }
        });

        $this->components->info("Listo: {$people->count()} matches para {$me->email}, $chats con conversación larga.");

        return self::SUCCESS;
    }

    /**
     * Personas de prueba compatibles con la cuenta y sin conexión previa con ella.
     *
     * @return Collection<int, User>
     */
    private function candidates(User $me): Collection
    {
        $wants = ['Mujeres' => 'Mujer', 'Hombres' => 'Hombre'];
        $myAudience = array_search($me->gender, $wants, true);
        $connected = Connection::query()->where('user_one_id', $me->id)->pluck('user_two_id')
            ->merge(Connection::query()->where('user_two_id', $me->id)->pluck('user_one_id'));

        return User::query()
            ->where('email', 'like', '%@'.FakeUsers::DOMAIN)
            ->whereNotIn('id', $connected)
            ->when(array_intersect_key($wants, array_flip($me->interested_in ?? [])), fn ($q, $genders) => $q->whereIn('gender', array_values($genders)))
            ->where(fn ($q) => $q->whereJsonContains('interested_in', 'Todas las personas')
                ->when($myAudience !== false, fn ($q) => $q->orWhereJsonContains('interested_in', $myAudience)))
            ->inRandomOrder()
            ->get();
    }

    private function conversation(Connection $connection, User $me, User $person, CarbonImmutable $from, CarbonImmutable $until, int $length, bool $short = false): void
    {
        $interest = Arr::random($person->interests ?: ['el café']);
        $lines = $short
            ? collect(range(1, $length))->map(fn (int $i) => [$i % 2 ? 'them' : 'me', Arr::random(self::OPENERS)])->all()
            : array_slice(self::SCRIPT, 0, $length);

        // Mensajes en orden, repartidos entre el match y ahora (los ULID siguen ese orden).
        $step = max(60, intdiv($until->getTimestamp() - $from->getTimestamp(), count($lines) + 1));
        $at = $from;

        foreach ($lines as [$who, $text]) {
            $at = $at->addSeconds(random_int((int) ($step * 0.3), $step));
            $message = $connection->messages()->make([
                'sender_id' => $who === 'me' ? $me->id : $person->id,
                'body' => strtr($text, ['{interest}' => mb_strtolower($interest), '{name}' => $person->name]),
            ]);
            $message->forceFill(['created_at' => min($at, $until)])->save();
        }

        $connection->forceFill(['last_message_at' => min($at, $until)])->save();
    }
}
