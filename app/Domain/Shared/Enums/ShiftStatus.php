<?php

namespace App\Domain\Shared\Enums;

enum ShiftStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
}
