<?php

namespace App\Notifications\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class TaskDueDateChangedNotification extends BaseNotification
{
    public function __construct(
        private readonly User $recipient,
        private readonly Task $task,
        private readonly ?string $oldDueDate,
        private readonly ?string $newDueDate,
    ) {}

    protected function getRecipientId(): int
    {
        return $this->recipient->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'task_due_date_changed',
            'title' => __('notifications.task_due_date_changed_title'),
            'body' => __('notifications.task_due_date_changed_body', [
                'task' => $this->task->title,
                'new_due_date' => $this->newDueDate ?? __('notifications.no_due_date'),
            ]),
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'project_id' => $this->task->project_id,
            'old_due_date' => $this->oldDueDate,
            'new_due_date' => $this->newDueDate,
            'url' => '/projects/'.$this->task->project_id.'/tasks/'.$this->task->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
