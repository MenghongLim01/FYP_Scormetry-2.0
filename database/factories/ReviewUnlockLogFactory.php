<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\ReviewUnlockLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewUnlockLog>
 */
class ReviewUnlockLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_id' => Review::factory(),
            'team_id' => Team::factory(),
            'judge_id' => User::factory()->teacher(),
            'unlocked_by' => User::factory()->teacher(),
            'reason' => fake()->sentence(),
        ];
    }
}
