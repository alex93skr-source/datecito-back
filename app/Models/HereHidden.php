<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Persona de la que alguien decidió ocultarse en "Aquí y ahora" (aplica en ambas direcciones).
 */
#[Fillable(['user_id', 'hidden_user_id'])]
class HereHidden extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'here_hidden';
}
