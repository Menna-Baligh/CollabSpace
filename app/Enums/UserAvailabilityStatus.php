<?php

namespace App\Enums;

enum UserAvailabilityStatus: string
{
    case AVAILABLE = 'available';
    case BUSY = 'busy';
    case AWAY = 'away';
    case OFFLINE = 'offline';

    /**
     * Get all enum values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
