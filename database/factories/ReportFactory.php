<?php

namespace Database\Factories;

use App\Enums\ReportType;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => Project::factory(),
            'report_type' => ReportType::PROJECT_PROGRESS,
            'note' => fake()->sentence(),
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->toDateString(),
            'summary_snapshot' => [
                'evaluated' => 10,
                'completed' => 5,
            ],
        ];
    }
}
