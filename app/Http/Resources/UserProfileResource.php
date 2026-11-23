<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserProfileResource extends JsonResource
{
    /**
     * Additional data to be injected into resource.
     *
     * @var array{completed_tasks_count?: int, recent_activities?: mixed}
     */
    protected array $extraData = [];

    /**
     * Set extra data for the profile resource.
     *
     * @param  array{completed_tasks_count?: int, recent_activities?: mixed}  $data
     */
    public function withExtra(array $data): self
    {
        $this->extraData = $data;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $completedTasksCount = $this->extraData['completed_tasks_count']
            ?? $this->assignedTasks()->where('tasks.status', 'completed')->count();

        $recentActivities = $this->extraData['recent_activities']
            ?? $this->activities()->latest('id')->limit(10)->get();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'job_title' => $this->job_title,
            'joined_at' => $this->created_at?->format('M Y'),
            'availability_status' => $this->availability_status instanceof \BackedEnum
                ? $this->availability_status->value
                : $this->availability_status,
            'stats' => [
                'completed_tasks_count' => (int) $completedTasksCount,
                'hours_worked' => (int) $this->hours_worked,
            ],
            'about' => [
                'bio' => $this->bio,
            ],
            'recent_activities' => UserActivityResource::collection($recentActivities),
        ];
    }
}
