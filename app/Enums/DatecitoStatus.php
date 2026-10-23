<?php

namespace App\Enums;

/**
 * Estado de una propuesta de datecito.
 */
enum DatecitoStatus: string
{
    case Proposed = 'proposed';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
