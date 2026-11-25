<?php

namespace App\Notifications\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class TaskStatusChangedNotification extends BaseNotification
{
    public function __construct(
        private readonly User $recipient,
        private readonly Task $task,
        private readonly string $oldStatus,
        private readonly string $newStatus,
        private readonly User $changedBy,
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
            'type' => 'task_status_changed',
            'title' => __('notifications.task_status_changed_title'),
            'body' => __('notifications.task_status_changed_body', [
                'task' => $this->task->title,
                'old_status' => $this->oldStatus,
                'new_status' => $this->newStatus,
                'by' => $this->changedBy->name,
            ]),
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'project_id' => $this->task->project_id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'url' => '/projects/'.$this->task->project_id.'/tasks/'.$this->task->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
