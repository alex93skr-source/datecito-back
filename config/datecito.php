<?php

/**
 * Reglas de negocio de Datecito.
 *
 * Precios: Plan de infraestructura, inversión y monetización (oct 2026).
 */
return [

    /*
    | Durante el lanzamiento Datecito es gratis para todos. Al poner esto en
    | false, sólo quienes tengan una suscripción activa usan la app.
    */
    'launch_free' => (bool) env('DATECITO_LAUNCH_FREE', true),

    /*
    | Sólo para desarrollo: acepta compras simuladas sin validar con Google
    | Play / App Store. Nunca se respeta fuera de APP_ENV=local o testing.
    */
    'fake_purchases' => (bool) env('DATECITO_FAKE_PURCHASES', false),

    /*
    | Sólo para desarrollo: aprueba la selfie de verificación al instante.
    */
    'auto_verify' => (bool) env('DATECITO_AUTO_VERIFY', false),

    /*
    | CRM: segundos que se cachean las métricas (0 = sin caché, útil en pruebas).
    */
    'crm' => [
        'cache_seconds' => (int) env('DATECITO_CRM_CACHE', 60),
        // Orígenes web del CRM, además de CORS_ALLOWED_ORIGINS.
        'url' => env('DATECITO_CRM_URL', 'http://localhost:5175'),
    ],

    /*
    | Zona horaria para los límites diarios (se reinician a medianoche) y para
    | escribir fechas de datecitos.
    */
    'timezone' => env('DATECITO_TIMEZONE', 'America/Mexico_City'),

    'pricing' => [
        // Precios de referencia para México (decisión del 22 oct 2026); el cobro real lo hace la tienda.
        'currency' => 'MXN',
        'monthly' => ['product_id' => 'datecito_monthly', 'price' => 200],
        'yearly' => ['product_id' => 'datecito_yearly', 'price' => 1999, 'weekly_boosts' => 1],
        'super_like_pack' => ['product_id' => 'datecito_superlikes_10', 'price' => 59, 'quantity' => 10],
    ],

    'limits' => [
        'photos_max' => 7,
        'photos_min' => 2,
        // Ritmo diario: evita que, con pocas personas en la zona, se acaben en un día.
        'likes_per_day' => (int) env('DATECITO_LIKES_PER_DAY', 30),
        'profiles_per_day' => (int) env('DATECITO_PROFILES_PER_DAY', 40),
        'super_like_every_days' => (int) env('DATECITO_SUPER_LIKE_EVERY_DAYS', 3),
        // Quien pasaste de largo vuelve a aparecer después de estos días (segunda oportunidad).
        'pass_returns_after_days' => 14,
        'interests_max' => 10,
        'teams_per_sport' => 2,
        'genres_max' => 6,
        'prompts_max' => 3,
        'trusted_contacts_max' => 3,
        'message_max_length' => 1000,
        'discover_batch' => 20,
        'here_minutes' => [60, 120, 180],
        // Datecito Seguro: cada cuánto se pregunta "¿todo bien?".
        'safe_check_in_minutes' => [15, 30, 45, 60],
        // Radio para considerar que dos personas están "en el mismo lugar".
        'here_radius_meters' => 150,
        'email_code_ttl_minutes' => 10,
        'email_code_max_attempts' => 5,
        'min_age' => 18,
    ],

    /*
    | Fotos: se recodifican a WebP (sin metadatos/GPS) en estos anchos.
    */
    'photos' => [
        'disk' => env('DATECITO_PHOTOS_DISK', 's3'),
        'signing_disk' => env('DATECITO_PHOTOS_SIGNING_DISK', 's3_public'),
        'url_ttl_minutes' => 30,
        'quality' => 80,
        'variants' => ['lg' => 1080, 'md' => 640, 'sm' => 200],
    ],

    'catalog' => [
        'genders' => ['Mujer', 'Hombre', 'No binarie', 'Prefiero describirlo'],
        'interested_in' => ['Mujeres', 'Hombres', 'Todas las personas'],
        'intents' => ['Conocer a alguien especial', 'Algo serio, con calma', 'Conocer sin presión', 'Todavía lo estoy descubriendo'],
        'relationship_types' => ['Monógama', 'Relación abierta', 'No monógama', 'Prefiero platicarlo'],
        // Nunca se quita ni renombra un interés: hay perfiles que ya lo tienen guardado.
        'interests' => [
            'Comida' => ['Tacos', 'Cocina', 'Repostería', 'Brunch', 'Comida vegana', 'Sushi', 'Comida callejera', 'Mariscos', 'Pizza', 'Restaurantes nuevos'],
            'Bebidas' => ['Café', 'Mezcal', 'Vino', 'Cerveza artesanal', 'Cocteles', 'Tequila', 'Micheladas', 'Pulque', 'Té', 'Sin alcohol'],
            'Deportes' => ['Fútbol', 'Fútbol americano', 'Básquetbol', 'Béisbol', 'Box', 'Lucha libre', 'Fórmula 1', 'Tenis', 'Pádel', 'Gym', 'Running', 'Ciclismo', 'Natación', 'Yoga', 'Escalada'],
            'Aire libre' => ['Senderismo', 'Campismo', 'Playa', 'Pueblos mágicos', 'Atardeceres'],
            'Cultura' => ['Cine', 'Museos', 'Teatro', 'Libros', 'Arte', 'Fotografía', 'Arquitectura', 'Diseño', 'Stand-up', 'Historia'],
            'Música y fiesta' => ['Conciertos', 'Vinilos', 'Karaoke', 'Bailar', 'Festivales', 'Tocar un instrumento', 'Antros', 'Cantinas'],
            'En casa' => ['Videojuegos', 'Series', 'Anime', 'Juegos de mesa', 'Plantas', 'Podcasts', 'Cerámica', 'Manualidades'],
            'Viajes y causas' => ['Viajes', 'Road trips', 'Voluntariado', 'Mascotas', 'Medio ambiente', 'Idiomas'],
        ],
        // "¿A qué equipo le vas?": aparece al elegir el deporte en intereses.
        'teams' => [
            'Fútbol' => [
                'Liga MX' => ['América', 'Chivas', 'Cruz Azul', 'Pumas', 'Tigres', 'Rayados', 'Toluca', 'Santos Laguna', 'Pachuca', 'León', 'Atlas', 'Puebla', 'Querétaro', 'Necaxa', 'Mazatlán', 'FC Juárez', 'Xolos', 'Atlético de San Luis'],
                'Internacional' => ['Selección Mexicana', 'Real Madrid', 'Barcelona', 'Manchester United', 'Manchester City', 'Liverpool', 'Chelsea', 'Arsenal', 'Bayern Múnich', 'PSG', 'Juventus', 'Milan', 'Inter', 'Boca Juniors', 'River Plate'],
            ],
            'Fútbol americano' => [
                'NFL' => ['Cardinals', 'Falcons', 'Ravens', 'Bills', 'Panthers', 'Bears', 'Bengals', 'Browns', 'Cowboys', 'Broncos', 'Lions', 'Packers', 'Texans', 'Colts', 'Jaguars', 'Chiefs', 'Raiders', 'Chargers', 'Rams', 'Dolphins', 'Vikings', 'Patriots', 'Saints', 'Giants', 'Jets', 'Eagles', 'Steelers', '49ers', 'Seahawks', 'Buccaneers', 'Titans', 'Commanders'],
            ],
            'Básquetbol' => [
                'NBA' => ['Hawks', 'Celtics', 'Nets', 'Hornets', 'Bulls', 'Cavaliers', 'Mavericks', 'Nuggets', 'Pistons', 'Warriors', 'Rockets', 'Pacers', 'Clippers', 'Lakers', 'Grizzlies', 'Heat', 'Bucks', 'Timberwolves', 'Pelicans', 'Knicks', 'Thunder', 'Magic', '76ers', 'Suns', 'Trail Blazers', 'Kings', 'Spurs', 'Raptors', 'Jazz', 'Wizards'],
                'México' => ['Capitanes CDMX'],
            ],
            'Béisbol' => [
                'México' => ['Diablos Rojos del México', 'Sultanes de Monterrey', 'Toros de Tijuana', 'Charros de Jalisco', 'Tomateros de Culiacán', 'Naranjeros de Hermosillo', 'Águilas de Mexicali', 'Algodoneros de Unión Laguna'],
                'MLB' => ['Dodgers', 'Yankees', 'Red Sox', 'Cubs', 'Astros', 'Padres', 'Braves', 'Mets', 'Giants', 'Cardinals', 'Mariners', 'Rangers'],
            ],
        ],
        'genres' => ['Pop', 'Rock', 'Indie', 'Reggaetón', 'Regional mexicano', 'Electrónica', 'Jazz', 'Salsa', 'Hip hop', 'R&B', 'Cumbia', 'Trova', 'Alternativo', 'Clásica', 'Metal', 'K-pop'],
        'languages' => ['Español', 'Inglés', 'Francés', 'Portugués', 'Alemán', 'Italiano', 'Lengua de señas', 'Náhuatl'],
        'basics' => [
            'drink' => ['No tomo', 'Socialmente', 'Con frecuencia'],
            'smoke' => ['No fumo', 'A veces', 'Sí fumo'],
            'exercise' => ['Diario', 'Algunas veces', 'Casi nunca'],
            'pets' => ['Tengo perro', 'Tengo gato', 'Otra mascota', 'Sin mascotas', 'Quiero tener'],
            'kids' => ['Tengo hijos', 'Quiero algún día', 'No quiero', 'Aún no lo sé'],
            'education' => ['Preparatoria', 'Universidad', 'Posgrado', 'Otro'],
            'zodiac' => ['Aries', 'Tauro', 'Géminis', 'Cáncer', 'Leo', 'Virgo', 'Libra', 'Escorpio', 'Sagitario', 'Capricornio', 'Acuario', 'Piscis'],
        ],
        'prompts' => [
            'Mi datecito ideal',
            'Me ganas si…',
            'Un domingo perfecto…',
            'Mi red flag es…',
            'Algo que no sabías de mí',
            'Lo que más valoro',
            'La canción que no puedo dejar de escuchar',
            'Mi lugar favorito de la ciudad',
        ],
        'plan_kinds' => ['cafe', 'comida', 'bar', 'naturaleza', 'cultura', 'otro'],
        // Poses para la selfie de verificación (la app elige una al azar; el moderador comprueba que se cumpla).
        'verification_poses' => [
            'Levanta la mano derecha junto a tu cara',
            'Haz la señal de paz con los dedos',
            'Toca tu oreja izquierda',
            'Pon tu mano sobre tu cabeza',
            'Haz un corazón con las manos',
            'Muestra el pulgar arriba',
            'Tápate un ojo con la mano',
            'Pon la mano en tu barbilla',
        ],
        'report_reasons' => ['Comportamiento inapropiado', 'Perfil falso o spam', 'Me preocupa mi seguridad', 'Es menor de edad'],
    ],
];
