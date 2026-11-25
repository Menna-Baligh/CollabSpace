<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'period' => ['nullable', 'string', Rule::in(['this_year', 'this_month', 'this_week'])],
            'team_id' => ['nullable', 'integer', Rule::exists('project_teams', 'id')],
        ];
    }
}
