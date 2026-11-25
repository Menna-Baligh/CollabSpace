<?php

namespace App\Enums;

enum AiMessageRole: string
{
    case USER = 'user';
    case ASSISTANT = 'assistant';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
