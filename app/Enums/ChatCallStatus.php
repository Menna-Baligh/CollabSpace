<?php

namespace App\Enums;

enum ChatCallStatus: string
{
    case RINGING = 'ringing';
    case ACTIVE = 'active';
    case ENDED = 'ended';
    case REJECTED = 'rejected';
    case MISSED = 'missed';
}
