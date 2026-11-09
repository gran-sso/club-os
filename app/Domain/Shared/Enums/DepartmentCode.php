<?php

namespace App\Domain\Shared\Enums;

enum DepartmentCode: string
{
    case ADMIN = 'admin';
    case MEMBERSHIP = 'membership';
    case BAR = 'bar';
    case CARD_ROOM = 'card_room';
    case FOOD = 'food';
    case ACCOMMODATION = 'accommodation';
    case SPORTS = 'sports';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administration',
            self::MEMBERSHIP => 'Membership & Front Desk',
            self::BAR => 'Bar & Lounge',
            self::CARD_ROOM => 'Card Room',
            self::FOOD => 'Food & Restaurant',
            self::ACCOMMODATION => 'Rooms & Accommodation',
            self::SPORTS => 'Gym & Badminton',
        };
    }
}
