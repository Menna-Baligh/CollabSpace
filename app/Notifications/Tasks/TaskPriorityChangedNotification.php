<?php

namespace App\Notifications\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class TaskPriorityChangedNotification extends BaseNotification
{
    public function __construct(
        private readonly User $recipient,
        private readonly Task $task,
        private readonly string $oldPriority,
        private readonly string $newPriority,
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
            'type' => 'task_priority_changed',
            'title' => __('notifications.task_priority_changed_title'),
            'body' => __('notifications.task_priority_changed_body', [
                'task' => $this->task->title,
                'old_priority' => $this->oldPriority,
                'new_priority' => $this->newPriority,
            ]),
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'project_id' => $this->task->project_id,
            'old_priority' => $this->oldPriority,
            'new_priority' => $this->newPriority,
            'url' => '/projects/'.$this->task->project_id.'/tasks/'.$this->task->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
