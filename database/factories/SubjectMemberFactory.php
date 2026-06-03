<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\SubjectMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubjectMember>
 */
class SubjectMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'user_id' => User::factory()->student(),
            'role' => 'student',
            'status' => 'approved',
            'role_label' => null,
        ];
    }

    public function reviewer(string $role = 'advisor'): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory()->teacher(),
            'role' => $role,
            'status' => 'approved',
            'role_label' => match ($role) {
                'advisor' => 'Advisor',
                'fyp_instructor' => 'FYP Instructor',
                'guest_panel' => 'Guest Panel',
                default => null,
            },
        ]);
    }
}
