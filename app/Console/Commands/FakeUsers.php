<?php

namespace App\Console\Commands;

use App\Enums\SwipeType;
use App\Models\User;
use App\Services\PhotoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SOLO desarrollo: crea muchas personas de prueba con perfiles variados para ver
 * cómo se comporta la app con volumen real (Descubrir, filtros, matches).
 *
 * Las fotos salen de un banco compartido (photos/seed/...) que se procesa una
 * sola vez; así 1000 personas no necesitan 1000 descargas.
 */
#[Signature('datecito:fake-users
    {count=1000 : Cuántas personas crear}
    {--near=19.42,-99.16 : Centro "lat,lng" alrededor del cual se reparten}
    {--radius=20 : Radio en km}
    {--zones= : Colonias a mostrar, separadas por coma (por defecto, colonias de CDMX)}
    {--fresh : Borra antes las personas de prueba creadas por este comando}')]
#[Description('Crea personas de prueba con fotos y perfiles variados (solo local)')]
class FakeUsers extends Command
{
    public const DOMAIN = 'seed.datecito.test';

    private const PHOTO_URL = 'https://images.unsplash.com/%s?auto=format&fit=crop&w=1080&h=1350&q=80';

    /**
     * @var array<string, list<string>>
     */
    private const PHOTOS = [
        'Mujer' => [
            'photo-1494790108377-be9c29b29330', 'photo-1517841905240-472988babdf9', 'photo-1534528741775-53994a69daeb',
            'photo-1529626455594-4ff0802cfb7e', 'photo-1488426862026-3ee34a7d66df', 'photo-1531123897727-8f129e1688ce',
            'photo-1438761681033-6461ffad8d80', 'photo-1544005313-94ddf0286df2', 'photo-1487412720507-e7ab37603c6f',
            'photo-1524504388940-b1c1722653e1', 'photo-1502823403499-6ccfcf4fb453', 'photo-1508214751196-bcfd4ca60f91',
            'photo-1520813792240-56fc4a3765a7',
        ],
        'Hombre' => [
            'photo-1535713875002-d1d0cf377fde', 'photo-1504257432389-52343af06ae3', 'photo-1500648767791-00dcc994a43e',
            'photo-1507003211169-0a1dd7228f2d', 'photo-1506794778202-cad84cf45f1d', 'photo-1492562080023-ab3db95bfbce',
            'photo-1519085360753-af0119f7cbe7', 'photo-1472099645785-5658abf4ff4e', 'photo-1463453091185-61582044d556',
            'photo-1539571696357-5a69c17a67c6', 'photo-1521119989659-a83eee488004', 'photo-1508341591423-4347099e1f19',
            'photo-1531427186611-ecfd6d936c79',
        ],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const NAMES = [
        'Mujer' => ['Ana', 'María', 'Fernanda', 'Daniela', 'Sofía', 'Valeria', 'Camila', 'Regina', 'Ximena', 'Andrea', 'Paola', 'Mariana', 'Renata', 'Natalia', 'Lucía', 'Isabel', 'Gabriela', 'Alejandra', 'Karla', 'Itzel', 'Frida', 'Montserrat', 'Jimena', 'Paulina', 'Carolina', 'Diana', 'Elena', 'Abril', 'Romina', 'Luisa'],
        'Hombre' => ['Diego', 'Carlos', 'Luis', 'Jorge', 'Andrés', 'Emilio', 'Santiago', 'Mateo', 'Sebastián', 'Rodrigo', 'Pablo', 'Javier', 'Miguel', 'Alejandro', 'Ricardo', 'Fernando', 'Iván', 'Óscar', 'Eduardo', 'Arturo', 'Héctor', 'Raúl', 'Daniel', 'Gerardo', 'Tomás', 'Bruno', 'Leonardo', 'Rafael', 'Hugo', 'Adrián'],
        'No binarie' => ['Alex', 'Sam', 'Ari', 'Dani', 'Cris', 'Robin', 'Noa', 'Iza', 'Kai', 'Elian'],
    ];

    private const ZONES = ['Roma Norte', 'Condesa', 'Juárez', 'Coyoacán', 'Del Valle', 'Narvarte', 'Polanco', 'San Rafael', 'Santa María la Ribera', 'Escandón', 'Centro', 'Nápoles', 'Portales', 'Tlalpan', 'San Ángel', 'Doctores', 'Anzures', 'Mixcoac'];

    private const JOBS = ['Diseñadora gráfica', 'Ingeniero de software', 'Abogada', 'Médico', 'Chef', 'Arquitecta', 'Maestra', 'Contador', 'Fotógrafo', 'Psicóloga', 'Músico', 'Enfermera', 'Mercadóloga', 'Veterinario', 'Barista', 'Periodista', 'Emprendedor', 'Estudiante', 'Consultora', 'Ilustradora'];

    private const BIOS = [
        'Fan de los domingos lentos, el café de olla y las pláticas que se alargan.',
        'Busco a alguien con quien probar todos los tacos de la ciudad.',
        'Me gustan los planes sencillos: caminar, platicar y reírnos un rato.',
        'Si hay concierto, probablemente esté ahí. Si no, en casa con un buen libro.',
        'Cocino mejor de lo que bailo, pero bailo con ganas.',
        'Siempre tengo una recomendación de serie y una playlist para cada ocasión.',
        'Amante de los perros, los mercados y los atardeceres en azotea.',
        'Corro por las mañanas y compenso con pan dulce por la tarde.',
        'Viajero de fin de semana. Pregúntame por mi pueblo mágico favorito.',
        'Me encantan los museos gratis del domingo y las cantinas con rockola.',
    ];

    private const ANSWERS = [
        'Un café, caminar sin rumbo y terminar en una taquería.',
        'Me recomiendas un libro que no conozca.',
        'Mercado, chilaquiles y una siesta sin culpa.',
        'Me río de mis chistes antes de terminarlos.',
        'Sé hacer pan de muerto desde cero.',
        'La gente que escucha de verdad.',
        'Algo de Natalia Lafourcade en repetición.',
        'Cualquier azotea con vista al atardecer.',
    ];

    public function handle(PhotoService $photoService): int
    {
        if (! app()->environment('local')) {
            $this->components->error('Este comando sólo funciona en local.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $deleted = User::query()->where('email', 'like', '%@'.self::DOMAIN)->delete();
            $this->components->info("Personas de prueba anteriores borradas: $deleted");
        }

        $pool = $this->photoPool($photoService);

        if ($pool['Mujer'] === [] || $pool['Hombre'] === []) {
            $this->components->error('No se pudieron preparar las fotos (¿sin internet?).');

            return self::FAILURE;
        }

        [$lat, $lng] = array_map('floatval', explode(',', (string) $this->option('near')));
        $count = (int) $this->argument('count');
        $start = User::query()->where('email', 'like', '%@'.self::DOMAIN)->count();
        $password = Hash::make(Str::random(32));
        $created = [];

        $this->components->task("Creando $count personas", function () use ($count, $start, $lat, $lng, $password, $pool, &$created): void {
            foreach (array_chunk(range(1, $count), 250) as $chunk) {
                $users = [];
                $photos = [];

                foreach ($chunk as $n) {
                    $user = $this->profile($start + $n, $lat, $lng, $password);
                    $users[] = $user;
                    $created[] = $user['id'];

                    $gender = $user['gender'] === 'No binarie' ? Arr::random(['Mujer', 'Hombre']) : $user['gender'];
                    foreach (array_values(Arr::random($pool[$gender], min(count($pool[$gender]), random_int(2, 4)))) as $position => $key) {
                        $photos[] = ['id' => (string) Str::ulid(), 'user_id' => $user['id'], 'position' => $position, 'storage_key' => $key, 'created_at' => now(), 'updated_at' => now()];
                    }
                }

                DB::transaction(function () use ($users, $photos): void {
                    DB::table('users')->insert($users);
                    DB::table('photos')->insert($photos);
                });
            }
        });

        $this->components->task('Agregando likes hacia las cuentas reales', fn () => $this->likeRealAccounts($created));

        $this->components->info("Listo: $count personas de prueba alrededor de {$lat},{$lng} (radio {$this->option('radius')} km).");

        return self::SUCCESS;
    }

    /**
     * Descarga y procesa una sola vez el banco de fotos.
     *
     * @return array<string, list<string>>
     */
    private function photoPool(PhotoService $photoService): array
    {
        $disk = Storage::disk(config('datecito.photos.disk'));
        $pool = [];

        foreach (self::PHOTOS as $gender => $ids) {
            $pool[$gender] = [];

            foreach ($ids as $id) {
                $key = 'photos/seed/'.$id;

                if (! $disk->exists($key.'/sm.webp')) {
                    $response = Http::timeout(30)->get(sprintf(self::PHOTO_URL, $id));

                    if (! $response->successful()) {
                        $this->components->warn("Foto omitida: $id");

                        continue;
                    }

                    $path = tempnam(sys_get_temp_dir(), 'seed');
                    file_put_contents($path, $response->body());
                    $photoService->writeVariants($key, new UploadedFile($path, 'seed.jpg', 'image/jpeg', null, true));
                    @unlink($path);
                }

                $pool[$gender][] = $key;
            }
        }

        return $pool;
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(int $number, float $lat, float $lng, string $password): array
    {
        $catalog = config('datecito.catalog');
        $gender = $this->weighted(['Mujer' => 47, 'Hombre' => 47, 'No binarie' => 6]);
        $verified = random_int(1, 100) <= 65;
        $interests = Arr::random(Arr::flatten($catalog['interests']), random_int(3, 8));

        // Reparte uniformemente dentro de un círculo; 2 decimales como en la app (≈ 1 km).
        $distance = (float) $this->option('radius') * sqrt(mt_rand() / mt_getrandmax());
        $angle = deg2rad(random_int(0, 359));

        return [
            'id' => (string) Str::ulid(),
            'email' => sprintf('persona%04d@%s', $number, self::DOMAIN),
            'email_verified_at' => now(),
            'password' => $password,
            'role' => 'user',
            'status' => 'active',
            'name' => Arr::random(self::NAMES[$gender]),
            'birthdate' => today()->subYears($this->weighted([random_int(18, 24) => 25, random_int(25, 34) => 50, random_int(35, 50) => 25]))->subDays(random_int(0, 364))->toDateString(),
            'gender' => $gender,
            'interested_in' => json_encode([$this->audience($gender)]),
            'job' => Arr::random(self::JOBS),
            'bio' => Arr::random(self::BIOS),
            'intent' => Arr::random($catalog['intents']),
            'relationship_type' => $this->weighted(['Monógama' => 70, 'Prefiero platicarlo' => 15, 'Relación abierta' => 8, 'No monógama' => 7]),
            'show_relationship' => random_int(0, 1),
            'zone' => Arr::random($this->zones()),
            'interests' => json_encode($interests),
            'teams' => json_encode((object) self::teamsFor($interests)),
            'music_genres' => json_encode(Arr::random($catalog['genres'], random_int(1, 5))),
            'music_artists' => json_encode([]),
            'basics' => json_encode(collect($catalog['basics'])->filter(fn () => random_int(0, 1))->map(fn (array $options) => Arr::random($options))->all()),
            'languages' => json_encode(array_values(array_unique(['Español', ...Arr::random($catalog['languages'], random_int(0, 2))]))),
            'prompts' => json_encode(collect(Arr::random($catalog['prompts'], random_int(1, 3)))->map(fn (string $q) => ['q' => $q, 'a' => Arr::random(self::ANSWERS)])->values()->all()),
            'approx_lat' => round($lat + ($distance / 111.0) * cos($angle), 2),
            'approx_lng' => round($lng + ($distance / (111.0 * cos(deg2rad($lat)))) * sin($angle), 2),
            'verification_status' => $verified ? 'approved' : 'none',
            'verified_at' => $verified ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Equipos para los deportes elegidos; la mayoría le va a alguno, pocos a dos.
     *
     * @param  list<string>  $interests
     * @return array<string, list<string>>
     */
    public static function teamsFor(array $interests): array
    {
        $teams = [];

        foreach (config('datecito.catalog.teams') as $sport => $leagues) {
            if (in_array($sport, $interests, true) && random_int(1, 100) <= 80) {
                $teams[$sport] = Arr::random(Arr::flatten($leagues), random_int(1, 100) <= 80 ? 1 : 2);
            }
        }

        return $teams;
    }

    /**
     * @return list<string>
     */
    private function zones(): array
    {
        $custom = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('zones')))));

        return $custom !== [] ? $custom : self::ZONES;
    }

    private function audience(string $gender): string
    {
        return match ($gender) {
            'Mujer' => $this->weighted(['Hombres' => 70, 'Todas las personas' => 18, 'Mujeres' => 12]),
            'Hombre' => $this->weighted(['Mujeres' => 70, 'Todas las personas' => 18, 'Hombres' => 12]),
            default => 'Todas las personas',
        };
    }

    /**
     * Una parte de las personas de prueba ya le dio like a cada cuenta real:
     * al darles like de regreso hay match y se puede probar el chat.
     *
     * @param  list<string>  $created
     */
    private function likeRealAccounts(array $created): void
    {
        $real = User::query()->where('email', 'not like', '%@'.self::DOMAIN)->pluck('id');

        foreach ($real as $targetId) {
            $rows = collect($created)
                ->filter(fn () => random_int(1, 100) <= 25)
                ->map(fn (string $id) => ['user_id' => $id, 'target_id' => $targetId, 'type' => (random_int(1, 100) <= 5 ? SwipeType::Super : SwipeType::Like)->value, 'created_at' => now()]);

            foreach ($rows->chunk(500) as $chunk) {
                DB::table('swipes')->insertOrIgnore($chunk->values()->all());
            }
        }
    }

    /**
     * @template T of array-key
     *
     * @param  array<T, int>  $weights
     * @return T
     */
    private function weighted(array $weights): int|string
    {
        $roll = random_int(1, array_sum($weights));

        foreach ($weights as $value => $weight) {
            if (($roll -= $weight) <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }
}
