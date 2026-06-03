<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'name' => fake()->words(2, true),
            'topic' => null,
            'defense_date' => null,
            'defense_time' => null,
            'defense_room' => null,
            'score_deadline_at' => null,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'defense_date' => now()->addDay()->toDateString(),
            'defense_time' => '09:00',
            'defense_room' => 'Room 305',
            'score_deadline_at' => now()->addDays(2),
        ]);
    }
}
