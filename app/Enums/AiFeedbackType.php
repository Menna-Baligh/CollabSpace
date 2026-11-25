<?php

namespace App\Enums;

enum AiFeedbackType: string
{
    case LIKE = 'like';
    case DISLIKE = 'dislike';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
