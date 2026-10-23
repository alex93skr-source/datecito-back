<?php

namespace App\Enums;

/**
 * Reacción a un perfil en Descubrir.
 */
enum SwipeType: string
{
    case Like = 'like';
    case Pass = 'pass';
    case Super = 'super';
}
