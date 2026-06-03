<?php

namespace Database\Seeders;

use App\Models\DefensePeriod;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds 20 realistic subjects across the existing teacher accounts. Each subject
 * gets unique join + reviewer codes and the default Midterm + Final defense
 * periods, so it behaves exactly like a teacher-created subject in the UI.
 *
 * Run: php artisan db:seed --class=TwentySubjectsSeeder
 * Idempotent — re-running won't duplicate (matches on title).
 */
class TwentySubjectsSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = User::where('role', 'teacher')->orderBy('id')->get();

        if ($teachers->isEmpty()) {
            $teachers = collect([
                User::factory()->teacher()->approved()->create([
                    'name' => 'Teacher 1',
                    'email' => 'teacher1@example.com',
                ]),
            ]);
        }

        /** @var array<int, array{title: string, description: string, pass: int}> $subjects */
        $subjects = [
            ['title' => 'FYP Capstone — Computer Science 2026', 'description' => 'Final-year capstone defenses for the BSCS program.', 'pass' => 50],
            ['title' => 'FYP Capstone — Information Technology 2026', 'description' => 'Capstone project evaluations for the BSIT program.', 'pass' => 50],
            ['title' => 'Software Engineering Defense', 'description' => 'Team software project defenses against the SE rubric.', 'pass' => 60],
            ['title' => 'Data Science Capstone', 'description' => 'Applied data-science capstone presentations and scoring.', 'pass' => 55],
            ['title' => 'Mobile App Development Project', 'description' => 'Mobile application capstone reviews.', 'pass' => 50],
            ['title' => 'Web Systems Capstone', 'description' => 'Full-stack web project defenses.', 'pass' => 50],
            ['title' => 'Cybersecurity Research Defense', 'description' => 'Security research project evaluations.', 'pass' => 60],
            ['title' => 'AI & Machine Learning Capstone', 'description' => 'Machine-learning capstone defenses.', 'pass' => 55],
            ['title' => 'IoT Systems Project', 'description' => 'Internet-of-Things prototype evaluations.', 'pass' => 50],
            ['title' => 'Cloud Computing Capstone', 'description' => 'Cloud-native project defenses.', 'pass' => 50],
            ['title' => 'Thesis Defense — Business IT', 'description' => 'Business information-systems thesis defenses.', 'pass' => 50],
            ['title' => 'Game Development Showcase', 'description' => 'Game design and development project judging.', 'pass' => 50],
            ['title' => 'UX/UI Design Capstone', 'description' => 'Design capstone reviews against a design rubric.', 'pass' => 55],
            ['title' => 'Network Engineering Project', 'description' => 'Network design and implementation defenses.', 'pass' => 60],
            ['title' => 'Database Systems Capstone', 'description' => 'Database project evaluations.', 'pass' => 50],
            ['title' => 'Embedded Systems Defense', 'description' => 'Embedded / hardware project defenses.', 'pass' => 60],
            ['title' => 'Research Symposium — ICT', 'description' => 'ICT faculty research symposium judging.', 'pass' => 50],
            ['title' => 'Innovation Challenge 2026', 'description' => 'Rubric-based judging for the annual innovation challenge.', 'pass' => 50],
            ['title' => 'Graduate Thesis Defense', 'description' => 'Graduate-level thesis defense evaluations.', 'pass' => 60],
            ['title' => 'Capstone — Multimedia & Animation', 'description' => 'Multimedia capstone showcase and scoring.', 'pass' => 50],
        ];

        $created = 0;
        foreach ($subjects as $i => $info) {
            $owner = $teachers[$i % $teachers->count()];

            $subject = Subject::firstOrCreate(
                ['title' => $info['title']],
                [
                    'description' => $info['description'],
                    'teacher_id' => $owner->id,
                    'passing_score' => $info['pass'],
                    'join_code' => $this->uniqueCode('join_code'),
                    'reviewer_code' => $this->uniqueCode('reviewer_code'),
                    'require_approval' => $i % 3 === 0, // mix of approval-required + auto-join
                ],
            );

            $this->seedDefaultPeriods($subject);
            $created++;
        }

        $this->command?->info("Seeded {$created} subjects (".Subject::count().' total in system).');
    }

    private function seedDefaultPeriods(Subject $subject): void
    {
        $periods = [
            ['name' => 'Midterm Defense', 'type' => 'midterm', 'sequence' => 1],
            ['name' => 'Final Defense', 'type' => 'final', 'sequence' => 2],
        ];

        foreach ($periods as $period) {
            DefensePeriod::firstOrCreate(
                ['subject_id' => $subject->id, 'type' => $period['type']],
                [
                    'name' => $period['name'],
                    'sequence' => $period['sequence'],
                    'score_scale' => 'points_100',
                    'passing_score' => $subject->passing_score ?: 50,
                    'status' => 'setup',
                ],
            );
        }
    }

    private function uniqueCode(string $column): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (Subject::where($column, $code)->exists());

        return $code;
    }
}
