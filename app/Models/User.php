<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Persona usuaria de Datecito.
 *
 * Los campos de rol, estado, verificación, saldo y ubicación no son asignables
 * masivamente: sólo los cambian servicios internos.
 */
#[Fillable([
    'email', 'password',
    'name', 'birthdate', 'gender', 'interested_in', 'job', 'bio', 'intent', 'relationship_type', 'zone',
    'interests', 'teams', 'music_genres', 'music_artists', 'anthem', 'basics', 'languages', 'prompts',
    'show_in_discover', 'show_gender', 'show_job', 'show_relationship',
    'only_verified_messages', 'i_write_first', 'offensive_warning', 'filters',
])]
#[Hidden(['password', 'approx_lat', 'approx_lng', 'filters'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    /**
     * Mismos valores por defecto que la migración, para modelos recién creados.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'user',
        'status' => 'active',
        'verification_status' => 'none',
        'show_in_discover' => true,
        'show_gender' => false,
        'show_job' => true,
        'show_relationship' => false,
        'only_verified_messages' => false,
        'i_write_first' => false,
        'offensive_warning' => true,
        'super_likes_balance' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verified_at' => 'datetime',
            'free_super_like_at' => 'datetime',
            'password' => 'hashed',
            'birthdate' => 'date',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'verification_status' => VerificationStatus::class,
            'interested_in' => 'array',
            'interests' => 'array',
            'teams' => 'array',
            'music_genres' => 'array',
            'music_artists' => 'array',
            'basics' => 'array',
            'languages' => 'array',
            'prompts' => 'array',
            'filters' => 'array',
            'show_in_discover' => 'boolean',
            'show_gender' => 'boolean',
            'show_job' => 'boolean',
            'show_relationship' => 'boolean',
            'only_verified_messages' => 'boolean',
            'i_write_first' => 'boolean',
            'offensive_warning' => 'boolean',
            'approx_lat' => 'float',
            'approx_lng' => 'float',
            'super_likes_balance' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Photo, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('position');
    }

    /**
     * @return HasMany<Swipe, $this>
     */
    public function swipes(): HasMany
    {
        return $this->hasMany(Swipe::class);
    }

    /**
     * @return HasMany<TrustedContact, $this>
     */
    public function trustedContacts(): HasMany
    {
        return $this->hasMany(TrustedContact::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<Block, $this>
     */
    public function blocksMade(): HasMany
    {
        return $this->hasMany(Block::class, 'blocker_id');
    }

    /**
     * @return HasMany<VerificationRequest, $this>
     */
    public function verificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class);
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reportsReceived(): HasMany
    {
        return $this->hasMany(Report::class, 'reported_id');
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reportsMade(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * @return HasMany<ActivityEvent, $this>
     */
    public function activityEvents(): HasMany
    {
        return $this->hasMany(ActivityEvent::class);
    }

    /**
     * @return HasMany<UserNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(UserNote::class);
    }

    /**
     * @return HasMany<UserTag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(UserTag::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function age(): ?int
    {
        return $this->birthdate?->age;
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function canModerate(): bool
    {
        return in_array($this->role, [UserRole::Moderator, UserRole::Admin], true);
    }

    /**
     * IDs de personas con las que existe un bloqueo en cualquier dirección.
     *
     * @return list<string>
     */
    public function blockedUserIds(): array
    {
        return Block::query()
            ->where('blocker_id', $this->id)
            ->pluck('blocked_id')
            ->merge(Block::query()->where('blocked_id', $this->id)->pluck('blocker_id'))
            ->unique()
            ->values()
            ->all();
    }

    public function hasBlockWith(User $other): bool
    {
        return Block::query()
            ->where(fn (Builder $q) => $q->where('blocker_id', $this->id)->where('blocked_id', $other->id))
            ->orWhere(fn (Builder $q) => $q->where('blocker_id', $other->id)->where('blocked_id', $this->id))
            ->exists();
    }

    /**
     * Personas que pueden aparecer a otras: cuenta activa y correo verificado.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    #[Scope]
    protected function reachable(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active)->whereNotNull('email_verified_at');
    }
}
