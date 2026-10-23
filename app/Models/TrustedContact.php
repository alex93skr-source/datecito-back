<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contacto de confianza para Datecito Seguro. Nombre y teléfono cifrados.
 */
#[Fillable(['name', 'phone'])]
class TrustedContact extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'name' => 'encrypted',
            'phone' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
