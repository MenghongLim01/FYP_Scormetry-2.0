<?php

namespace Database\Factories;

use App\Models\Paper;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'paper_id' => Paper::factory(),
            'reviewer_id' => User::factory()->teacher(),
            'committee_role' => null,
            'scores_json' => null,
            'comment' => null,
            'is_submitted' => false,
            'locked_at' => null,
            'unlocked_at' => null,
            'unlock_reason' => null,
            'unlocked_by' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_submitted' => true,
            'locked_at' => now(),
            'scores_json' => [
                ['criteria' => 'Content Quality', 'score' => fake()->numberBetween(1, 4), 'max_score' => 4, 'weight' => 50, 'comment' => null],
                ['criteria' => 'Presentation Quality', 'score' => fake()->numberBetween(1, 4), 'max_score' => 4, 'weight' => 50, 'comment' => null],
            ],
        ]);
    }
}
