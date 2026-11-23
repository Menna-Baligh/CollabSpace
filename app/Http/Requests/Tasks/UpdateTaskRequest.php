<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
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
        $project = $this->route('project');
        $task = $this->route('task');

        $projectId = $project instanceof Project
            ? $project->id
            : ($task instanceof Task ? $task->project_id : null);

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'string', Rule::in(TaskStatus::values())],
            'priority' => ['sometimes', 'required', 'string', Rule::in(TaskPriority::values())],
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
