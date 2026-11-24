<?php

namespace App\Enums;

enum MeetingParticipantStatus: string
{
    case INVITED = 'invited';
    case ACCEPTED = 'accepted';
    case DECLINED = 'declined';
}
