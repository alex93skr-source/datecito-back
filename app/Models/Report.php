<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reporte confidencial: la persona reportada nunca sabe quién lo envió.
 */
#[Fillable(['reported_id', 'reason', 'details'])]
#[Hidden(['details'])]
class Report extends Model
{
    use HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'details' => 'encrypted',
            'resolution_note' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reported(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
