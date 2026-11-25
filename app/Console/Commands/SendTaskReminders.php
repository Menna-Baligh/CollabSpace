<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskReminderLog;
use App\Notifications\Tasks\TaskDueSoonNotification;
use App\Notifications\Tasks\TaskOverdueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendTaskReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-task-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send due soon (24h) and overdue task reminder notifications to assignees';

    public function handle(): int
    {
        $now = now();
        $today = $now->toDateString();
        $tomorrow = $now->copy()->addHours(24)->toDateString();

        $dueSoonCount = 0;
        $overdueCount = 0;

        // 1. Due soon: due_date is today or tomorrow (within 24h), not completed, not soft-deleted, project not archived
        Task::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $tomorrow)
            ->where('status', '!=', TaskStatus::COMPLETED->value)
            ->whereHas('project', function ($q) {
                $q->where('status', '!=', ProjectStatus::ARCHIVED->value)
                    ->whereNull('archived_at');
            })
            ->with(['assignees', 'project:id,name'])
            ->chunkById(100, function ($tasks) use (&$dueSoonCount) {
                foreach ($tasks as $task) {
                    $dueDateStr = $task->due_date->toDateString();

                    $alreadySent = TaskReminderLog::where('task_id', $task->id)
                        ->where('reminder_type', 'due_soon')
                        ->whereDate('due_date', $dueDateStr)
                        ->exists();

                    if ($alreadySent) {
                        continue;
                    }

                    if ($task->assignees->isEmpty()) {
                        continue;
                    }

                    DB::transaction(function () use ($task, $dueDateStr, &$dueSoonCount) {
                        TaskReminderLog::create([
                            'task_id' => $task->id,
                            'reminder_type' => 'due_soon',
                            'due_date' => $dueDateStr,
                            'sent_at' => now(),
                        ]);

                        foreach ($task->assignees as $assignee) {
                            $assignee->notify(new TaskDueSoonNotification($assignee, $task));
                        }

                        $dueSoonCount++;
                    });
                }
            });

        // 2. Overdue: due_date < today, not completed, not soft-deleted, project not archived
        Task::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->where('status', '!=', TaskStatus::COMPLETED->value)
            ->whereHas('project', function ($q) {
                $q->where('status', '!=', ProjectStatus::ARCHIVED->value)
                    ->whereNull('archived_at');
            })
            ->with(['assignees', 'project:id,name'])
            ->chunkById(100, function ($tasks) use (&$overdueCount) {
                foreach ($tasks as $task) {
                    $dueDateStr = $task->due_date->toDateString();

                    $alreadySent = TaskReminderLog::where('task_id', $task->id)
                        ->where('reminder_type', 'overdue')
                        ->whereDate('due_date', $dueDateStr)
                        ->exists();

                    if ($alreadySent) {
                        continue;
                    }

                    if ($task->assignees->isEmpty()) {
                        continue;
                    }

                    DB::transaction(function () use ($task, $dueDateStr, &$overdueCount) {
                        TaskReminderLog::create([
                            'task_id' => $task->id,
                            'reminder_type' => 'overdue',
                            'due_date' => $dueDateStr,
                            'sent_at' => now(),
                        ]);

                        foreach ($task->assignees as $assignee) {
                            $assignee->notify(new TaskOverdueNotification($assignee, $task));
                        }

                        $overdueCount++;
                    });
                }
            });

        $this->info("Sent {$dueSoonCount} due-soon and {$overdueCount} overdue task reminders.");

        return self::SUCCESS;
    }
}
