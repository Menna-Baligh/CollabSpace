<?php

namespace App\Notifications\Projects;

use App\Models\Project;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class ProjectManagerAssignedNotification extends BaseNotification
{
    public function __construct(
        private readonly User $recipient,
        private readonly Project $project,
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
            'type' => 'project_manager_assigned',
            'title' => __('notifications.project_manager_assigned_title'),
            'body' => __('notifications.project_manager_assigned_body', [
                'project' => $this->project->name,
            ]),
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'url' => '/projects/'.$this->project->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
