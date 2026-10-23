<?php

namespace App\Models;

use App\Enums\EmailCodePurpose;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * Código de un solo uso enviado por correo. Sólo se guarda su HMAC.
 */
#[Fillable(['email', 'purpose', 'code_hash', 'expires_at'])]
#[Hidden(['code_hash'])]
class EmailCode extends Model
{
    protected function casts(): array
    {
        return [
            'purpose' => EmailCodePurpose::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}
