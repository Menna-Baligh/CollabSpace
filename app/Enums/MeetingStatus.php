<?php

namespace App\Enums;

enum MeetingStatus: string
{
    case SCHEDULED = 'scheduled';
    case LIVE = 'live';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
