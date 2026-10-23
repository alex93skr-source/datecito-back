<?php

namespace App\Enums;

/**
 * Rol del usuario dentro de la plataforma.
 */
enum UserRole: string
{
    case User = 'user';
    case Moderator = 'moderator';
    case Admin = 'admin';
}
