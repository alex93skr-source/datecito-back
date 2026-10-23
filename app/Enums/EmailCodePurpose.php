<?php

namespace App\Enums;

/**
 * Para qué sirve un código enviado por correo.
 */
enum EmailCodePurpose: string
{
    case VerifyEmail = 'verify_email';
    case ResetPassword = 'reset_password';
}
