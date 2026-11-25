<?php

namespace App\Notifications\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class TaskUnassignedNotification extends BaseNotification
{
    public function __construct(
        private readonly User $recipient,
        private readonly Task $task,
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
            'type' => 'task_unassigned',
            'title' => __('notifications.task_unassigned_title'),
            'body' => __('notifications.task_unassigned_body', [
                'task' => $this->task->title,
                'project' => $this->task->project->name ?? '',
            ]),
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'project_id' => $this->task->project_id,
            'url' => '/projects/'.$this->task->project_id.'/tasks/'.$this->task->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
