<?php

namespace App\Enums;

/**
 * Tienda donde se realizó la compra.
 */
enum PurchaseStore: string
{
    case GooglePlay = 'google_play';
    case AppStore = 'app_store';
    case Fake = 'fake';
}
