<?php

namespace App\Jobs;

use App\Models\ActivityEvent;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Guarda un evento de comportamiento y actualiza "última vez visto" de la persona.
 */
class RecordActivityEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}

    public function handle(): void
    {
        ActivityEvent::query()->create($this->attributes);

        $userId = $this->attributes['user_id'] ?? null;

        if ($userId === null || ($this->attributes['category'] ?? null) === ActivityEvent::CATEGORY_SYSTEM) {
            return;
        }

        $updates = ['last_seen_at' => now()];
        if (! empty($this->attributes['platform'])) {
            $updates['last_platform'] = $this->attributes['platform'];
        }
        if (! empty($this->attributes['app_version'])) {
            $updates['last_app_version'] = $this->attributes['app_version'];
        }

        // Se actualiza como mucho cada 5 minutos para no escribir en cada petición.
        User::query()
            ->whereKey($userId)
            ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subMinutes(5)))
            ->update($updates);
    }
}
