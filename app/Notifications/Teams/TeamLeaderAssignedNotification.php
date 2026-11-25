<?php

namespace App\Notifications\Teams;

use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class TeamLeaderAssignedNotification extends BaseNotification
{
    public function __construct(
        private readonly User $recipient,
        private readonly ProjectTeam $team,
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
            'type' => 'team_leader_assigned',
            'title' => __('notifications.team_leader_assigned_title'),
            'body' => __('notifications.team_leader_assigned_body', [
                'team' => $this->team->name,
                'project' => $this->project->name,
            ]),
            'team_id' => $this->team->id,
            'team_name' => $this->team->name,
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'url' => '/projects/'.$this->project->id.'/teams/'.$this->team->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
