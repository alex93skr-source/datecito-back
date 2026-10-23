<?php

namespace App\Models;

use App\Enums\PurchaseStore;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membresía mensual o anual comprada en Google Play o App Store.
 */
#[Fillable(['plan', 'store', 'store_reference', 'status', 'starts_at', 'expires_at'])]
class Subscription extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'plan' => SubscriptionPlan::class,
            'store' => PurchaseStore::class,
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCurrent(): bool
    {
        return $this->status === SubscriptionStatus::Active && $this->expires_at->isFuture();
    }
}
