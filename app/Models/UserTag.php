<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Etiqueta interna para segmentar personas desde el CRM (por ejemplo "vip" o "sospechoso").
 */
#[Fillable(['tag', 'created_by'])]
class UserTag extends Model
{
    public const UPDATED_AT = null;
}
