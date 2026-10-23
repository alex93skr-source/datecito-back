<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Saludo en "Aquí y ahora". Sólo se revela si es mutuo (entonces se crea la conexión).
 */
#[Fillable(['user_id', 'target_id'])]
class HereGreet extends Model
{
    public const UPDATED_AT = null;
}
