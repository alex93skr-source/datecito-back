<?php

namespace App\Enums;

/**
 * Estado de la cuenta.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Suspended = 'suspended';
    case Banned = 'banned';
}
