<?php

namespace App\Domain\Shared\Enums;

enum PassStatus: string
{
    case ACTIVE = 'active';
    case USED = 'used';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';

    public function isValid(): bool
    {
        return $this === self::ACTIVE;
    }
}
