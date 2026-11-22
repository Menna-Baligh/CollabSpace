<?php

namespace App\Enums;

enum ProjectRole: string
{
    case PROJECT_MANAGER = 'project_manager';
    case MEMBER = 'member';

    /**
     * Get all values as an array.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
