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
            'turned_in_at' => now(),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility_status' => 'published',
            'turned_in_at' => now(),
            'final_score' => fake()->randomFloat(2, 60, 100),
        ]);
    }

    /** An attached-but-not-yet-turned-in draft. */
    public function attached(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility_status' => 'draft',
            'turned_in_at' => null,
        ]);
    }
}
