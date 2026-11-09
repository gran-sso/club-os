<?php

namespace App\Domain\Shared\Enums;

enum TenderType: string
{
    case CASH = 'cash';
    case DIGITAL = 'digital';
    case PREPAID = 'prepaid';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash in Hand',
            self::DIGITAL => 'Digital (UPI / Card Terminal)',
            self::PREPAID => 'Prepaid Card / Member Wallet',
        };
    }
}
