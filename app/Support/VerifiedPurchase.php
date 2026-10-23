<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Resultado de una compra validada por la tienda.
 */
final readonly class VerifiedPurchase
{
    public function __construct(
        public string $productId,
        public string $storeReference,
        public ?CarbonImmutable $expiresAt = null,
    ) {}
}
