<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserSettingsResource extends JsonResource
{
    /**
     * Optional current team & project label.
     */
    protected ?string $currentTeamProject = null;

    /**
     * Set the current team & project label.
     */
    public function withCurrentTeamProject(?string $label): self
    {
        $this->currentTeamProject = $label;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'full_name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'availability_status' => $this->availability_status instanceof \BackedEnum
                ? $this->availability_status->value
                : $this->availability_status,
            'job_title' => $this->job_title,
            'years_of_experience' => $this->years_of_experience !== null ? (int) $this->years_of_experience : null,
            'bio' => $this->bio,
            'avatar_url' => $this->avatar_url,
            'current_team_project' => $this->currentTeamProject,
        ];
    }
}
