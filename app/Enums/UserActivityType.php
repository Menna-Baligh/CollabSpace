<?php

namespace App\Enums;

enum UserActivityType: string
{
    case TASK_COMPLETED = 'task_completed';
    case TASK_UPDATED = 'task_updated';
    case MEETING_JOINED = 'meeting_joined';
    case COMMENT_ADDED = 'comment_added';
    case FILE_UPLOADED = 'file_uploaded';

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
