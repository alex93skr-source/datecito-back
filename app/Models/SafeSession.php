<?php

namespace App\Models;

use App\Enums\SafeSessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sesión de Datecito Seguro durante una cita.
 */
#[Fillable(['datecito_id', 'contact_ids', 'check_in_at', 'check_in_minutes', 'share_location'])]
class SafeSession extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'status' => SafeSessionStatus::class,
            'contact_ids' => 'array',
            'check_in_at' => 'datetime',
            'help_requested_at' => 'datetime',
            'ended_at' => 'datetime',
            'share_location' => 'boolean',
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
     * @return BelongsTo<Datecito, $this>
     */
    public function datecito(): BelongsTo
    {
        return $this->belongsTo(Datecito::class);
    }
}
