<?php

namespace Database\Factories;

use App\Models\Rubric;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rubric>
 */
class RubricFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'pdf_path' => 'rubrics/'.fake()->uuid().'.pdf',
            'structure_json' => null,
            'status' => 'uploaded',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending_verification',
            'structure_json' => [
                ['criteria' => 'Content Quality', 'max_score' => 50, 'weight' => 50],
                ['criteria' => 'Presentation Quality', 'max_score' => 50, 'weight' => 50],
            ],
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'locked',
            'structure_json' => [
                ['criteria' => 'Content Quality', 'max_score' => 50, 'weight' => 50],
                ['criteria' => 'Presentation Quality', 'max_score' => 50, 'weight' => 50],
            ],
        ]);
    }
}
