<?php

namespace App\Enums;

enum ReportType: string
{
    case PROJECT_PROGRESS = 'project_progress';
    case TASKS_SUMMARY = 'tasks_summary';
    case TEAM_ACTIVITY = 'team_activity';
    case WORKER_PERFORMANCE = 'worker_performance';

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
