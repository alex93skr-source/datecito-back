<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento de comportamiento: una acción en la API o una pantalla/acción enviada por la app.
 *
 * Nunca contiene contenido privado (mensajes, contraseñas, ubicación exacta).
 */
#[Fillable(['user_id', 'name', 'category', 'properties', 'platform', 'app_version', 'ip_hash', 'status', 'duration_ms', 'occurred_at'])]
class ActivityEvent extends Model
{
    use HasFactory;

    public const CATEGORY_API = 'api';

    public const CATEGORY_CLIENT = 'client';

    public const CATEGORY_SYSTEM = 'system';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'status' => 'integer',
            'duration_ms' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<ActivityEvent>  $query
     * @return Builder<ActivityEvent>
     */
    #[Scope]
    protected function between(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to);
    }
}
