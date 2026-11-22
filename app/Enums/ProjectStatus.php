<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case ACTIVE = 'active';
    case DONE = 'done';
    case ARCHIVED = 'archived';

    /**
     * Get all values as an array.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Allowed statuses for normal project updates (excluding archived).
     *
     * @return list<string>
     */
    public static function updateableValues(): array
    {
        return [
            self::ACTIVE->value,
            self::DONE->value,
        ];
    }
}
