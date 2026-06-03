<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'teacher_id' => User::factory()->teacher(),
            'passing_score' => fake()->numberBetween(50, 80),
            'join_code' => Str::upper(Str::random(6)),
            'reviewer_code' => Str::upper(Str::random(6)),
        ];
    }
}
