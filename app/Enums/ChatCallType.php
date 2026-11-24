<?php

namespace App\Enums;

enum ChatCallType: string
{
    case VOICE = 'voice';
    case VIDEO = 'video';
}
