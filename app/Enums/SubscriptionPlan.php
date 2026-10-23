<?php

namespace App\Enums;

/**
 * Planes de membresía.
 */
enum SubscriptionPlan: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
