<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // $this->route('project') can be a Project model or project_id from body for global task creation
        $project = $this->route('project');
        $projectId = $project instanceof Project ? $project->id : ($project ?? $this->input('project_id'));

        return [
            'project_id' => [
                Rule::requiredIf(fn () => ! $this->route('project')),
                'nullable',
                'integer',
                'exists:projects,id',
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(TaskStatus::values())],
            'priority' => ['nullable', 'string', Rule::in(TaskPriority::values())],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'due_date' => ['nullable', 'date'],
            'project_team_id' => [
                'nullable',
                'integer',
                Rule::exists('project_teams', 'id')->where(function ($query) use ($projectId) {
                    if ($projectId) {
                        $query->where('project_id', $projectId);
                    }
                }),
            ],
            'reviewer_id' => [
                'nullable',
                'integer',
                Rule::exists('project_user', 'user_id')->where(function ($query) use ($projectId) {
                    if ($projectId) {
                        $query->where('project_id', $projectId);
                    }
                }),
            ],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('project_user', 'user_id')->where(function ($query) use ($projectId) {
                    if ($projectId) {
                        $query->where('project_id', $projectId);
                    }
                }),
            ],
        ];
    }
}
