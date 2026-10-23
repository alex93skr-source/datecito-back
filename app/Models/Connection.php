<?php

namespace App\Models;

use App\Enums\ConnectionSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Conexión (match) entre dos personas que se eligieron mutuamente.
 */
#[Fillable(['user_one_id', 'user_two_id', 'source'])]
class Connection extends Model
{
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'source' => ConnectionSource::class,
            'last_message_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * Ordena el par para que (A, B) y (B, A) sean la misma conexión.
     *
     * @return array{0: string, 1: string}
     */
    public static function orderedPair(string $a, string $b): array
    {
        return strcmp($a, $b) < 0 ? [$a, $b] : [$b, $a];
    }

    public static function between(User $a, User $b): ?self
    {
        [$one, $two] = self::orderedPair($a->id, $b->id);

        return self::query()->where('user_one_id', $one)->where('user_two_id', $two)->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('id');
    }

    /**
     * @return HasMany<Datecito, $this>
     */
    public function datecitos(): HasMany
    {
        return $this->hasMany(Datecito::class);
    }

    public function involves(User $user): bool
    {
        return $this->user_one_id === $user->id || $this->user_two_id === $user->id;
    }

    public function otherId(User $user): string
    {
        return $this->user_one_id === $user->id ? $this->user_two_id : $this->user_one_id;
    }

    public function other(User $user): User
    {
        return $this->user_one_id === $user->id ? $this->userTwo : $this->userOne;
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * @param  Builder<Connection>  $query
     * @return Builder<Connection>
     */
    #[Scope]
    protected function forUser(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id));
    }

    /**
     * @param  Builder<Connection>  $query
     * @return Builder<Connection>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }
}
