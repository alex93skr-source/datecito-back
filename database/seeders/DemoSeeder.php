<?php

namespace Database\Seeders;

use App\Enums\ConnectionSource;
use App\Enums\DatecitoStatus;
use App\Enums\SwipeType;
use App\Models\User;
use App\Services\ConnectionService;
use App\Services\PhotoService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Datos de demostración SOLO para desarrollo local (las mismas personas de la maqueta).
 *
 * Cuenta demo: demo@datecito.test / Datecito2026
 * Personas demo: <nombre>@datecito.test / Datecito2026
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Datecito2026';

    private const PHOTO = 'https://images.unsplash.com/%s?auto=format&fit=crop&w=1080&q=80';

    public function __construct(private PhotoService $photos, private ConnectionService $connections) {}

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder no se ejecuta en producción.');

            return;
        }

        $demo = $this->person('demo', [
            'name' => 'Alex', 'birthdate' => today()->subYears(28)->toDateString(), 'gender' => 'Hombre', 'interested_in' => ['Todas las personas'],
            'job' => 'Product designer', 'zone' => 'Juárez', 'intent' => 'Conocer a alguien especial', 'relationship_type' => 'Monógama',
            'bio' => 'Siempre en búsqueda de una conversación que se alargue más de lo planeado. Colecciono libros usados y recetas fallidas.',
            'interests' => ['Diseño', 'Vinilos', 'Senderismo', 'Café', 'Cine'], 'music_genres' => ['Indie', 'Rock', 'Jazz'],
            'music_artists' => ['Zoé', 'Arctic Monkeys'], 'anthem' => 'Love — Zoé',
            'basics' => ['drink' => 'Socialmente', 'smoke' => 'No fumo', 'exercise' => 'Algunas veces', 'pets' => 'Tengo perro', 'zodiac' => 'Sagitario'],
            'languages' => ['Español', 'Inglés'], 'prompts' => [['q' => 'Mi datecito ideal', 'a' => 'Una caminata por Chapultepec y tacos al final.']],
        ], ['photo-1535713875002-d1d0cf377fde', 'photo-1504257432389-52343af06ae3'], 19.43, -99.16);

        $people = collect($this->people())->mapWithKeys(fn (array $p, string $key) => [
            $key => $this->person($key, $p['profile'], $p['photos'], $p['lat'], $p['lng'], $p['verified']),
        ]);

        // Personas que "ya le dieron like" a la cuenta demo: al darles like hay match.
        foreach (['mar', 'andres', 'sofia'] as $key) {
            $people[$key]->swipes()->firstOrCreate(['target_id' => $demo->id], ['type' => SwipeType::Like]);
        }

        $mar = $this->connections->open($demo, $people['mar'], ConnectionSource::Discover);
        $diego = $this->connections->open($demo, $people['diego'], ConnectionSource::Discover);
        $this->connections->open($demo, $people['camila'], ConnectionSource::Discover);
        foreach (['mar', 'diego', 'camila'] as $key) {
            $demo->swipes()->firstOrCreate(['target_id' => $people[$key]->id], ['type' => SwipeType::Like]);
        }

        if (! $mar->messages()->exists()) {
            foreach ([
                [$people['mar'], 'Me llamó la atención que te guste perderte en librerías 📚'],
                [$demo, 'Culpable. ¿Tienes alguna favorita?'],
                [$people['mar'], 'El Péndulo de la Roma, sin duda. ¿Y tú?'],
                [$people['mar'], 'El domingo suena perfecto ✨'],
            ] as [$sender, $body]) {
                $mar->messages()->create(['sender_id' => $sender->id, 'body' => $body]);
            }
            $mar->forceFill(['last_message_at' => now()])->save();

            $diego->messages()->create(['sender_id' => $demo->id, 'body' => '¿De verdad 200 vinilos de salsa?']);
            $diego->messages()->create(['sender_id' => $people['diego']->id, 'body' => 'Y contando. La pasé muy bien el sábado 🙌']);
            $diego->forceFill(['last_message_at' => now()])->save();

            $mar->datecitos()->create(['proposer_id' => $demo->id, 'kind' => 'cafe', 'place' => 'Café Avellaneda', 'zone' => 'Coyoacán', 'when_label' => 'Domingo por la mañana', 'scheduled_on' => today()->next('Sunday')->toDateString()])
                ->forceFill(['status' => DatecitoStatus::Confirmed, 'confirmed_at' => now()])->save();
            $diego->datecitos()->create(['proposer_id' => $people['diego']->id, 'kind' => 'comida', 'place' => 'Mercado de San Juan', 'zone' => 'Centro', 'when_label' => 'El sábado pasado', 'scheduled_on' => today()->subDays(2)->toDateString()])
                ->forceFill(['status' => DatecitoStatus::Confirmed, 'confirmed_at' => now()->subDays(3)])->save();

            $demo->trustedContacts()->create(['name' => 'Fer (hermana)', 'phone' => '55 1234 5678']);
        }

        $this->command?->info('Cuenta demo lista: demo@datecito.test');
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  list<string>  $photoIds
     */
    private function person(string $key, array $profile, array $photoIds, float $lat, float $lng, bool $verified = true): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $key.'@datecito.test'],
            [...$profile, 'password' => self::PASSWORD],
        );
        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
            'approx_lat' => $lat,
            'approx_lng' => $lng,
            'verified_at' => $verified ? ($user->verified_at ?? now()) : null,
            'verification_status' => $verified ? 'approved' : 'none',
        ])->save();

        if (! $user->photos()->exists()) {
            foreach ($photoIds as $photoId) {
                $this->uploadPhoto($user, $photoId);
            }
        }

        return $user;
    }

    private function uploadPhoto(User $user, string $photoId): void
    {
        $response = Http::timeout(30)->get(sprintf(self::PHOTO, $photoId));

        if (! $response->successful()) {
            $this->command?->warn("No se pudo descargar la foto $photoId (¿sin internet?).");

            return;
        }

        $path = tempnam(sys_get_temp_dir(), 'demo').'.jpg';
        file_put_contents($path, $response->body());
        $this->photos->store($user, new UploadedFile($path, 'demo.jpg', 'image/jpeg', null, true));
        @unlink($path);
    }

    /**
     * @return array<string, array{profile: array<string, mixed>, photos: list<string>, lat: float, lng: float, verified: bool}>
     */
    private function people(): array
    {
        $base = fn (string $name, int $age, string $gender, string $zone, string $job, string $bio, string $intent) => [
            'name' => $name, 'birthdate' => today()->subYears($age)->subDays(40)->toDateString(), 'gender' => $gender,
            'interested_in' => ['Todas las personas'], 'zone' => $zone, 'job' => $job, 'bio' => $bio, 'intent' => $intent,
        ];

        return [
            'mar' => ['profile' => [...$base('Mar', 27, 'Mujer', 'Roma Norte', 'Ceramista', 'Amante de los domingos lentos, los mercados de flores y las sobremesas que se alargan.', 'Algo serio, con calma'),
                'relationship_type' => 'Monógama', 'show_relationship' => true, 'interests' => ['Cerámica', 'Café', 'Cine', 'Plantas', 'Libros'],
                'music_genres' => ['Indie', 'Trova', 'Jazz'], 'music_artists' => ['Natalia Lafourcade', 'Bon Iver'], 'anthem' => 'Hasta la raíz — Natalia Lafourcade',
                'basics' => ['drink' => 'Socialmente', 'smoke' => 'No fumo', 'pets' => 'Tengo gato', 'kids' => 'Quiero algún día', 'zodiac' => 'Piscis'],
                'languages' => ['Español', 'Inglés'], 'prompts' => [['q' => 'Mi datecito ideal', 'a' => 'Café por la mañana y caminar sin rumbo por la Roma.'], ['q' => 'Me ganas si…', 'a' => 'Me recomiendas una librería que no conozca.']]],
                'photos' => ['photo-1494790108377-be9c29b29330', 'photo-1517841905240-472988babdf9'], 'lat' => 19.42, 'lng' => -99.16, 'verified' => true],
            'diego' => ['profile' => [...$base('Diego', 30, 'Hombre', 'Condesa', 'Chef', 'Cocino para relajarme y busco quien me diga honestamente si me quedó salado.', 'Conocer a alguien especial'),
                'interests' => ['Cocina', 'Vinilos', 'Ciclismo', 'Tacos', 'Bailar'], 'music_genres' => ['Salsa', 'Rock', 'Cumbia'], 'music_artists' => ['Héctor Lavoe', 'Café Tacvba'],
                'anthem' => 'El cantante — Héctor Lavoe', 'basics' => ['drink' => 'Socialmente', 'smoke' => 'No fumo', 'exercise' => 'Algunas veces', 'pets' => 'Tengo perro', 'education' => 'Universidad'],
                'languages' => ['Español', 'Italiano'], 'prompts' => [['q' => 'Mi datecito ideal', 'a' => 'Mercado de San Juan y luego cocinar juntos lo que compremos.'], ['q' => 'Algo que no sabías de mí', 'a' => 'Tengo una colección de 200 vinilos de salsa.']]],
                'photos' => ['photo-1500648767791-00dcc994a43e', 'photo-1507003211169-0a1dd7228f2d'], 'lat' => 19.41, 'lng' => -99.17, 'verified' => true],
            'sofia' => ['profile' => [...$base('Sofía', 29, 'Mujer', 'Coyoacán', 'Arquitecta', 'Siempre buscando una terraza nueva. Fan de los atardeceres y las playlists compartidas.', 'Conocer sin presión'),
                'relationship_type' => 'Prefiero platicarlo', 'show_relationship' => true, 'interests' => ['Arquitectura', 'Senderismo', 'Conciertos', 'Viajes', 'Café'],
                'music_genres' => ['Electrónica', 'Pop', 'Alternativo'], 'music_artists' => ['Bad Bunny', 'Caloncho'], 'anthem' => 'Optimista — Caloncho',
                'basics' => ['drink' => 'Socialmente', 'exercise' => 'Diario', 'pets' => 'Quiero tener', 'zodiac' => 'Leo', 'education' => 'Posgrado'],
                'languages' => ['Español', 'Inglés', 'Francés'], 'prompts' => [['q' => 'Un domingo perfecto…', 'a' => 'Una terraza con buena vista y mejor conversación.'], ['q' => 'Lo que más valoro', 'a' => 'La gente que pregunta y de verdad escucha.']]],
                'photos' => ['photo-1534528741775-53994a69daeb', 'photo-1529626455594-4ff0802cfb7e'], 'lat' => 19.35, 'lng' => -99.16, 'verified' => false],
            'andres' => ['profile' => [...$base('Andrés', 28, 'Hombre', 'Juárez', 'Diseñador UX', 'Corro los domingos en Chapultepec y compenso con pan dulce. Equilibrio, le dicen.', 'Algo serio, con calma'),
                'relationship_type' => 'Monógama', 'show_relationship' => true, 'interests' => ['Running', 'Diseño', 'Museos', 'Fotografía', 'Café'],
                'music_genres' => ['Indie', 'R&B', 'Hip hop'], 'music_artists' => ['Rosalía', 'Frank Ocean'], 'anthem' => 'Pink + White — Frank Ocean',
                'basics' => ['drink' => 'No tomo', 'smoke' => 'No fumo', 'exercise' => 'Diario', 'kids' => 'Aún no lo sé', 'zodiac' => 'Virgo'],
                'languages' => ['Español', 'Inglés'], 'prompts' => [['q' => 'Mi datecito ideal', 'a' => 'Museo por la tarde y cena en un lugar pequeñito.'], ['q' => 'Mi red flag es…', 'a' => 'Me río de mis propios chistes antes de terminarlos.']]],
                'photos' => ['photo-1506794778202-cad84cf45f1d', 'photo-1492562080023-ab3db95bfbce'], 'lat' => 19.43, 'lng' => -99.16, 'verified' => true],
            'camila' => ['profile' => [...$base('Camila', 26, 'Mujer', 'San Rafael', 'Editora', 'Café, cine de arte y conversaciones largas. Te presto libros si prometes devolverlos.', 'Todavía lo estoy descubriendo'),
                'relationship_type' => 'Relación abierta', 'interests' => ['Libros', 'Cine', 'Teatro', 'Vino', 'Karaoke'],
                'music_genres' => ['Pop', 'Trova', 'K-pop'], 'music_artists' => ['Julieta Venegas', 'NewJeans'],
                'basics' => ['drink' => 'Socialmente', 'smoke' => 'A veces', 'pets' => 'Sin mascotas', 'zodiac' => 'Acuario'],
                'languages' => ['Español', 'Portugués'], 'prompts' => [['q' => 'Mi datecito ideal', 'a' => 'Función en la Cineteca y debate posterior con mezcal.'], ['q' => 'Un domingo perfecto…', 'a' => 'Bazar, chilaquiles y siesta sin culpa.']]],
                'photos' => ['photo-1517841905240-472988babdf9', 'photo-1488426862026-3ee34a7d66df'], 'lat' => 19.44, 'lng' => -99.16, 'verified' => true],
            'valeria' => ['profile' => [...$base('Valeria', 31, 'Mujer', 'Narvarte', 'Médica veterinaria', 'Rescato perritos y busco a alguien que no le tenga miedo a los pelos en la ropa.', 'Algo serio, con calma'),
                'interests' => ['Mascotas', 'Senderismo', 'Yoga', 'Series'], 'music_genres' => ['Pop', 'Regional mexicano'], 'music_artists' => ['Carín León'],
                'basics' => ['drink' => 'Socialmente', 'pets' => 'Tengo perro', 'exercise' => 'Diario'], 'languages' => ['Español'],
                'prompts' => [['q' => 'Me ganas si…', 'a' => 'Llegas con premios para mis perros.']]],
                'photos' => ['photo-1531123897727-8f129e1688ce'], 'lat' => 19.40, 'lng' => -99.15, 'verified' => true],
            'emilio' => ['profile' => [...$base('Emilio', 33, 'Hombre', 'Del Valle', 'Ingeniero de sonido', 'Si hay un concierto, probablemente esté ahí. Si no, en una cantina con buena rockola.', 'Conocer sin presión'),
                'interests' => ['Conciertos', 'Vinilos', 'Mezcal', 'Fútbol'], 'music_genres' => ['Rock', 'Electrónica', 'Alternativo'], 'music_artists' => ['Daft Punk', 'Caifanes'],
                'basics' => ['drink' => 'Con frecuencia', 'smoke' => 'No fumo'], 'languages' => ['Español', 'Inglés'],
                'prompts' => [['q' => 'La canción que no puedo dejar de escuchar', 'a' => 'La célula que explota, versión en vivo.']]],
                'photos' => ['photo-1519085360753-af0119f7cbe7'], 'lat' => 19.38, 'lng' => -99.17, 'verified' => false],
        ];
    }
}
