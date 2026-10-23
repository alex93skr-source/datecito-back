<?php

namespace App\Services\Billing;

use App\Contracts\PurchaseVerifier;
use App\Enums\PurchaseStore;
use App\Exceptions\DomainRuleException;
use App\Support\VerifiedPurchase;
use Carbon\CarbonImmutable;

/**
 * Verificador simulado para desarrollo local y pruebas. Nunca se registra en producción.
 */
class FakePurchaseVerifier implements PurchaseVerifier
{
    public function verify(PurchaseStore $store, string $productId, string $purchaseToken): VerifiedPurchase
    {
        if ($store !== PurchaseStore::Fake) {
            throw new DomainRuleException('store_not_configured', 'Los pagos con la tienda aún no están configurados.', 503);
        }

        $pricing = config('datecito.pricing');
        $expiresAt = match ($productId) {
            $pricing['monthly']['product_id'] => CarbonImmutable::now()->addMonth(),
            $pricing['yearly']['product_id'] => CarbonImmutable::now()->addYear(),
            default => null,
        };

        return new VerifiedPurchase($productId, 'fake-'.$purchaseToken, $expiresAt);
    }
}
