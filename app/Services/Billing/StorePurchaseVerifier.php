<?php

namespace App\Services\Billing;

use App\Contracts\PurchaseVerifier;
use App\Enums\PurchaseStore;
use App\Exceptions\DomainRuleException;
use App\Support\VerifiedPurchase;

/**
 * Verificación real con Google Play Developer API / App Store Server API.
 *
 * Pendiente: requiere la cuenta de Google Play Console y las credenciales de
 * servicio. Mientras tanto rechaza cualquier compra para no regalar acceso.
 */
class StorePurchaseVerifier implements PurchaseVerifier
{
    public function verify(PurchaseStore $store, string $productId, string $purchaseToken): VerifiedPurchase
    {
        throw new DomainRuleException('store_not_configured', 'Los pagos con la tienda aún no están configurados.', 503);
    }
}
