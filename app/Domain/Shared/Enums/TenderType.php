<?php

namespace App\Domain\Shared\Enums;

enum TenderType: string
{
    case CASH = 'cash';
    case DIGITAL = 'digital';
    case PREPAID = 'prepaid';
    case BANK_TRANSFER = 'bank_transfer';
    case CHEQUE = 'cheque';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash in Hand',
            self::DIGITAL => 'Digital (UPI / Card Terminal)',
            self::PREPAID => 'Prepaid Card / Member Wallet',
            self::BANK_TRANSFER => 'Bank Direct Transfer (NEFT/RTGS)',
            self::CHEQUE => 'Bank Cheque / DD',
        };
    }
}
