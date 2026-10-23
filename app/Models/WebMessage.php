<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensaje recibido desde un formulario del sitio web.
 *
 * kind: contact | partners | newsletter | arco | campus. status: new | read | answered | spam.
 */
#[Fillable(['kind', 'lang', 'name', 'email', 'topic', 'company', 'city', 'message', 'attachment_key', 'ip_hash'])]
class WebMessage extends Model
{
    use HasUlids;

    public const KINDS = ['contact', 'partners', 'newsletter', 'arco', 'campus'];

    public const STATUSES = ['new', 'read', 'answered', 'spam'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'new', 'lang' => 'es'];

    protected function casts(): array
    {
        return [
            'message' => 'encrypted',
            'handled_at' => 'datetime',
        ];
    }

    /**
     * Folio que ve la persona (ARCO exige acuse): últimos 8 caracteres del id, en mayúsculas.
     */
    public function folio(): string
    {
        return strtoupper(($this->kind === 'arco' ? 'ARCO-' : 'MSG-').substr($this->id, -8));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
