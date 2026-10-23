<?php

namespace App\Enums;

/**
 * Estado de una suscripción.
 */
enum SubscriptionStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
