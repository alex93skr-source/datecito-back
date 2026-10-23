<?php

namespace App\Enums;

/**
 * Estado de la verificación con selfie.
 */
enum VerificationStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
