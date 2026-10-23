<?php

namespace App\Models;

use App\Enums\DatecitoStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Propuesta de plan entre dos personas con conexión. Ambas confirman.
 */
#[Fillable(['proposer_id', 'kind', 'place', 'zone', 'when_label', 'scheduled_on', 'scheduled_at', 'note'])]
class Datecito extends Model
{
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'proposed'];

    protected function casts(): array
    {
        return [
            'status' => DatecitoStatus::class,
            'scheduled_on' => 'date',
            'scheduled_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Connection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    /**
     * @return HasMany<DatecitoFeedback, $this>
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(DatecitoFeedback::class);
    }

    public function isPast(): bool
    {
        return $this->scheduled_on !== null && $this->scheduled_on->isBefore(today());
    }
}
