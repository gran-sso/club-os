<?php

namespace App\Domain\Shared\Enums;

enum BillStatus: string
{
    case DRAFT = 'draft';
    case PAID = 'paid';
    case REVERSED = 'reversed';
    case REFUNDED = 'refunded';
}
