<?php

namespace App\Enums;

enum PayoutMethodType: string
{
    case MPESA = 'mpesa';
    case AIRTEL = 'airtel';
    case MTN = 'mtn';
    case BANK = 'bank';

    public function label(): string
    {
        return match ($this) {
            self::MPESA => 'M-Pesa',
            self::AIRTEL => 'Airtel Money',
            self::MTN => 'MTN Mobile Money',
            self::BANK => 'Bank Transfer',
        };
    }

    public function isMobileMoney(): bool
    {
        return in_array($this, [self::MPESA, self::AIRTEL, self::MTN]);
    }
}
