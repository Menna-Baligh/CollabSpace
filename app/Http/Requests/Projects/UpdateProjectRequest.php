<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectRequest extends FormRequest
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
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_type' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'priority' => ['sometimes', Rule::enum(ProjectPriority::class)],
            // Mandatory Requirement 2: Allow changing status only between active and done.
            'status' => [
                'sometimes',
                Rule::in(ProjectStatus::updateableValues()),
            ],
            'color' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:50'],
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
            'status.in' => __('projects.status_archived_not_allowed'),
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var Project|null $project */
            $project = $this->route('project');

            $startDate = $this->input('start_date', $project?->start_date?->format('Y-m-d'));
            $deadline = $this->input('deadline', $project?->deadline?->format('Y-m-d'));

            if ($startDate && $deadline) {
                if (Carbon::parse($deadline)->lessThan(Carbon::parse($startDate))) {
                    $validator->errors()->add('deadline', __('projects.invalid_dates'));
                }
            }
        });
    }
}
