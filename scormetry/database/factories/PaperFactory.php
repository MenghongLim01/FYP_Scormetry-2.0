<?php

namespace Database\Factories;

use App\Models\Paper;
use App\Models\Subject;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paper>
 */
class PaperFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'subject_id' => Subject::factory(),
            'file_path' => 'papers/'.fake()->uuid().'.pdf',
            'final_score' => null,
            'visibility_status' => 'draft',
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility_status' => 'submitted',
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility_status' => 'published',
            'final_score' => fake()->randomFloat(2, 60, 100),
        ]);
    }
}
