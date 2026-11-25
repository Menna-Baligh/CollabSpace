<?php

namespace App\Notifications\Projects;

use App\Models\Project;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class ProjectMemberAddedNotification extends BaseNotification
{
    public function __construct(
        private readonly User $recipient,
        private readonly Project $project,
        private readonly string $role,
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
            'type' => 'project_member_added',
            'title' => __('notifications.project_member_added_title'),
            'body' => __('notifications.project_member_added_body', [
                'project' => $this->project->name,
                'role' => $this->role,
            ]),
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'role' => $this->role,
            'url' => '/projects/'.$this->project->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
