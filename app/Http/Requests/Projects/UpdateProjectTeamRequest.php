<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use App\Models\ProjectTeam;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectTeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');
        $projectId = $project instanceof Project ? $project->id : (int) $project;

        /** @var ProjectTeam $team */
        $team = $this->route('team');
        $teamId = $team instanceof ProjectTeam ? $team->id : (int) $team;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('project_teams', 'name')
                    ->where('project_id', $projectId)
                    ->ignore($teamId),
            ],
            'color' => ['nullable', 'string', 'max:50'],
            'leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'leader_role_title' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('projects.team_name_already_exists'),
        ];
    }
}
