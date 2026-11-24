<?php

namespace App\Enums;

enum MeetingRecordingStatus: string
{
    case NONE = 'none';
    case RECORDING = 'recording';
    case AVAILABLE = 'available';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
