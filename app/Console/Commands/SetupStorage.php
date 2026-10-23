<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Crea el bucket privado de fotos si no existe (SeaweedFS en local, R2 en producción).
 */
#[Signature('datecito:storage-setup')]
#[Description('Crea el bucket privado de fotos si no existe')]
class SetupStorage extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk(config('datecito.photos.disk'));
        $client = $disk->getClient();
        $bucket = $disk->getConfig()['bucket'];

        if ($client->doesBucketExistV2($bucket)) {
            $this->components->info("El bucket [$bucket] ya existe.");

            return self::SUCCESS;
        }

        $client->createBucket(['Bucket' => $bucket]);
        $this->components->info("Bucket [$bucket] creado (privado).");

        return self::SUCCESS;
    }
}
