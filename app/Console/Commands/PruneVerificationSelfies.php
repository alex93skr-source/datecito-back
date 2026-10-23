<?php

namespace App\Console\Commands;

use App\Enums\VerificationStatus;
use App\Models\VerificationRequest;
use App\Models\WebMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Cumple lo que promete la Política de datos sensibles: las selfies de verificación ya revisadas
 * se borran a los 30 días (se conserva sólo el resultado), igual que las identificaciones adjuntas
 * a solicitudes ARCO ya atendidas.
 */
#[Signature('datecito:prune-selfies {--days=30 : Días después de la revisión}')]
#[Description('Borra selfies de verificación e identificaciones ARCO ya revisadas')]
class PruneVerificationSelfies extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk(config('datecito.photos.disk'));
        $before = now()->subDays((int) $this->option('days'));
        $removed = 0;

        VerificationRequest::query()
            ->where('status', '!=', VerificationStatus::Pending)
            ->where('reviewed_at', '<', $before)
            ->where('selfie_key', '!=', '')
            ->each(function (VerificationRequest $request) use ($disk, &$removed): void {
                $disk->delete($request->selfie_key);
                $request->forceFill(['selfie_key' => ''])->save();
                $removed++;
            });

        WebMessage::query()
            ->whereNotNull('attachment_key')
            ->whereIn('status', ['answered', 'spam'])
            ->where('handled_at', '<', $before)
            ->each(function (WebMessage $message) use ($disk, &$removed): void {
                $disk->delete($message->attachment_key);
                $message->forceFill(['attachment_key' => null])->save();
                $removed++;
            });

        $this->components->info("Archivos borrados: $removed");

        return self::SUCCESS;
    }
}
