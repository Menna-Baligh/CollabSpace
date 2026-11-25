<?php

namespace App\Notifications\Files;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class ProjectFileUploadedNotification extends BaseNotification
{
    public function __construct(
        private readonly User $recipient,
        private readonly ProjectFile $file,
        private readonly Project $project,
        private readonly User $uploader,
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
            'type' => 'project_file_uploaded',
            'title' => __('notifications.project_file_uploaded_title'),
            'body' => __('notifications.project_file_uploaded_body', [
                'file' => $this->file->name,
                'project' => $this->project->name,
                'by' => $this->uploader->name,
            ]),
            'file_id' => $this->file->id,
            'file_name' => $this->file->name,
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'uploader_id' => $this->uploader->id,
            'uploader_name' => $this->uploader->name,
            'url' => '/projects/'.$this->project->id.'/files',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
