<?php

namespace App\Domain\Shared\Enums;

enum MemberStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case EXPIRED = 'expired';
    case RESIGNED = 'resigned';
    case TERMINATED = 'terminated';

    public function canAccess(): bool
    {
        return $this === self::ACTIVE;
    }

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::EXPIRED => 'Expired',
            self::RESIGNED => 'Resigned',
            self::TERMINATED => 'Terminated',
        };
    }
}
