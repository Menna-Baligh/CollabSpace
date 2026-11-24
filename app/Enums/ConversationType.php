<?php

namespace App\Enums;

enum ConversationType: string
{
    case DIRECT = 'direct';
    case PROJECT_CHANNEL = 'project_channel';
    case TEAM_CHANNEL = 'team_channel';
}
