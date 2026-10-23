<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Respuestas privadas de "¿Cómo te fue?". Cifradas y nunca visibles para la otra persona.
 */
#[Fillable(['user_id', 'answers'])]
class DatecitoFeedback extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'datecito_feedback';

    protected function casts(): array
    {
        return ['answers' => 'encrypted:array'];
    }
}
