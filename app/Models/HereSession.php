<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sesión temporal de "Aquí y ahora". Las coordenadas son aproximadas y nunca se exponen.
 */
#[Fillable(['place', 'lat', 'lng', 'verified_only', 'ends_at'])]
#[Hidden(['lat', 'lng'])]
class HereSession extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'verified_only' => 'boolean',
            'ends_at' => 'datetime',
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
     * @param  Builder<HereSession>  $query
     * @return Builder<HereSession>
     */
    #[Scope]
    protected function live(Builder $query): Builder
    {
        return $query->where('ends_at', '>', now());
    }
}
