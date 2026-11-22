<?php

namespace Database\Factories;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 month', '+1 month');
        $deadline = (clone $startDate)->modify('+2 months');

        return [
            'name' => fake()->unique()->sentence(3),
            'description' => fake()->paragraph(),
            'project_type' => fake()->randomElement(['Web Development', 'Mobile App', 'Design System', 'Marketing']),
            'start_date' => $startDate->format('Y-m-d'),
            'deadline' => $deadline->format('Y-m-d'),
            'status' => ProjectStatus::ACTIVE,
            'priority' => fake()->randomElement(ProjectPriority::cases()),
            'color' => fake()->hexColor(),
            'icon' => fake()->randomElement(['folder', 'briefcase', 'star', 'code', 'cpu']),
            'archived_at' => null,
        ];
    }

    /**
     * Indicate that the project is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::ARCHIVED,
            'archived_at' => now(),
        ]);
    }

    /**
     * Indicate that the project is done.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::DONE,
            'archived_at' => null,
        ]);
    }

    /**
     * Indicate that the project is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::ACTIVE,
            'archived_at' => null,
        ]);
    }
}
