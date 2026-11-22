<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectTeam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectTeam>
 */
class ProjectTeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->hexColor(),
            'leader_id' => null,
        ];
    }
}
