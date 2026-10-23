<?php

namespace App\Contracts;

use App\Enums\PurchaseStore;
use App\Exceptions\DomainRuleException;
use App\Support\VerifiedPurchase;

/**
 * Valida una compra contra la tienda (Google Play / App Store) en el servidor.
 * Nunca se confía en lo que diga el celular sobre un pago.
 */
interface PurchaseVerifier
{
    /**
     * @throws DomainRuleException cuando la compra no es válida.
     */
    public function verify(PurchaseStore $store, string $productId, string $purchaseToken): VerifiedPurchase;
}
