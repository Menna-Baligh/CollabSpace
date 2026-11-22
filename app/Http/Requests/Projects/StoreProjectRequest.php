<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_type' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'priority' => ['nullable', Rule::enum(ProjectPriority::class)],
            'status' => ['nullable', Rule::in([ProjectStatus::ACTIVE->value, ProjectStatus::DONE->value])],
            'color' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $startDate = $this->input('start_date');
            $deadline = $this->input('deadline');

            if ($startDate && $deadline) {
                if (Carbon::parse($deadline)->lessThan(Carbon::parse($startDate))) {
                    $validator->errors()->add('deadline', __('projects.invalid_dates'));
                }
            }
        });
    }
}
