<?php

namespace App\Enums;

/**
 * Cómo se originó una conexión (match).
 */
enum ConnectionSource: string
{
    case Discover = 'discover';
    case Here = 'here';
}
