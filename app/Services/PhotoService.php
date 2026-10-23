<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Fotos de perfil.
 *
 * Cada foto se vuelve a codificar a WebP en varios tamaños: así se eliminan los
 * metadatos (incluida la ubicación GPS de la cámara) y cualquier contenido
 * malicioso incrustado. Los archivos son privados y se sirven con enlaces
 * firmados que caducan.
 */
class PhotoService
{
    private ImageManager $images;

    public function __construct()
    {
        $this->images = new ImageManager(new Driver);
    }

    public function store(User $user, UploadedFile $file): Photo
    {
        $key = 'photos/'.$user->id.'/'.Str::ulid();
        $this->writeVariants($key, $file);

        try {
            return DB::transaction(function () use ($user, $key): Photo {
                $count = Photo::query()->where('user_id', $user->id)->lockForUpdate()->count();

                if ($count >= config('datecito.limits.photos_max')) {
                    throw new DomainRuleException('photos_limit', 'Puedes tener hasta '.config('datecito.limits.photos_max').' fotos.');
                }

                return $user->photos()->create(['position' => $count, 'storage_key' => $key]);
            });
        } catch (\Throwable $e) {
            $this->deleteFiles($key);

            throw $e;
        }
    }

    public function delete(Photo $photo): void
    {
        DB::transaction(function () use ($photo): void {
            $photo->delete();
            $this->normalizePositions($photo->user_id);
        });

        Cache::forget($this->urlCacheKey($photo));
        $this->deleteFiles($photo->storage_key);
    }

    /**
     * @param  list<string>  $orderedIds
     */
    public function reorder(User $user, array $orderedIds): void
    {
        DB::transaction(function () use ($user, $orderedIds): void {
            $photos = Photo::query()->where('user_id', $user->id)->lockForUpdate()->get()->keyBy('id');

            if ($photos->count() !== count($orderedIds) || $photos->keys()->diff($orderedIds)->isNotEmpty()) {
                throw new DomainRuleException('photos_order_invalid', 'El orden de fotos no coincide con tus fotos.');
            }

            foreach (array_values($orderedIds) as $position => $id) {
                $photos[$id]->update(['position' => $position]);
            }
        });
    }

    public function deleteAllFor(User $user): void
    {
        Storage::disk(config('datecito.photos.disk'))->deleteDirectory('photos/'.$user->id);
        Storage::disk(config('datecito.photos.disk'))->deleteDirectory('verification/'.$user->id);
    }

    /**
     * Enlaces firmados y temporales para cada tamaño.
     *
     * @return array<string, string>
     */
    public function urls(Photo $photo): array
    {
        $ttl = (int) config('datecito.photos.url_ttl_minutes');

        // Se reutiliza el enlace firmado hasta 5 min antes de que venza: menos CPU y el celular
        // puede aprovechar su caché de imágenes.
        return Cache::remember($this->urlCacheKey($photo), now()->addMinutes($ttl - 5), function () use ($photo, $ttl): array {
            $disk = Storage::disk(config('datecito.photos.signing_disk'));
            $expires = now()->addMinutes($ttl);

            return collect(array_keys(config('datecito.photos.variants')))
                ->mapWithKeys(fn (string $variant) => [$variant => $disk->temporaryUrl($photo->storage_key.'/'.$variant.'.webp', $expires)])
                ->all();
        });
    }

    private function urlCacheKey(Photo $photo): string
    {
        return 'photo-urls:'.$photo->id;
    }

    /**
     * Guarda la selfie de verificación (sólo un tamaño, privada).
     */
    public function storeSelfie(User $user, UploadedFile $file): string
    {
        $key = 'verification/'.$user->id.'/'.Str::ulid().'.webp';
        $encoded = $this->images->read($file->getRealPath())
            ->scaleDown(width: 1080)
            ->toWebp(quality: (int) config('datecito.photos.quality'));

        Storage::disk(config('datecito.photos.disk'))->put($key, (string) $encoded);

        return $key;
    }

    /**
     * Recodifica la imagen a WebP en cada tamaño y la guarda bajo el prefijo indicado.
     */
    public function writeVariants(string $key, UploadedFile $file): void
    {
        $disk = Storage::disk(config('datecito.photos.disk'));

        foreach (config('datecito.photos.variants') as $variant => $width) {
            $encoded = $this->images->read($file->getRealPath())
                ->scaleDown(width: $width)
                ->toWebp(quality: (int) config('datecito.photos.quality'));

            $disk->put($key.'/'.$variant.'.webp', (string) $encoded);
        }
    }

    private function deleteFiles(string $key): void
    {
        Storage::disk(config('datecito.photos.disk'))->deleteDirectory($key);
    }

    private function normalizePositions(string $userId): void
    {
        Photo::query()->where('user_id', $userId)->orderBy('position')->get()
            ->each(fn (Photo $photo, int $index) => $photo->position !== $index ? $photo->update(['position' => $index]) : null);
    }
}
