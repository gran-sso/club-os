<?php

namespace App\Domain\Shared\Enums;

enum CardStatus: string
{
    case ACTIVE = 'active';
    case BLOCKED = 'blocked';
    case REPLACED = 'replaced';

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
