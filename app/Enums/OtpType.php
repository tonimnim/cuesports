<?php

namespace App\Enums;

enum OtpType: string
{
    case EMAIL_VERIFICATION = 'email_verification';
    case PHONE_VERIFICATION = 'phone_verification';
    case PASSWORD_RESET = 'password_reset';
    case LOGIN = 'login';

    public function expiryMinutes(): int
    {
        return match ($this) {
            self::EMAIL_VERIFICATION => 30,
            self::PHONE_VERIFICATION => 10,
            self::PASSWORD_RESET => 15,
            self::LOGIN => 5,
        };
    }
}
