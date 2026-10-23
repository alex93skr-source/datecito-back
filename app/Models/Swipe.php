<?php

namespace App\Models;

use App\Enums\SwipeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reacción (like, pass o super like) de una persona hacia otra.
 */
#[Fillable(['target_id', 'type'])]
class Swipe extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['type' => SwipeType::class];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }
}
