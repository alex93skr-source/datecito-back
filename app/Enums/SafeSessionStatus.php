<?php

namespace App\Enums;

/**
 * Estado de una sesión de Datecito Seguro.
 */
enum SafeSessionStatus: string
{
    case Active = 'active';
    case Help = 'help';
    case Overdue = 'overdue';
    case Ended = 'ended';
}
