<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\DefenseAttempt;
use App\Models\DefenseAttemptReviewer;
use App\Models\DefensePeriod;
use App\Models\Paper;
use App\Models\Review;
use App\Models\Rubric;
use App\Models\Subject;
use App\Models\SubjectMember;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds the system with the dummy users + sample FYP world used by the
 * Scormetry 2.0 acceptance-testing checklist:
 *
 *   - 1 admin, 10 teachers (teacher1..10), 30 students (student1..30)
 *   - 1 FYP subject ("FYP Capstone 2026") owned by teacher1
 *   - 6 teachers attached as reviewers (mix of advisor / fyp_instructor / guest_panel)
 *   - 18 teams covering both solo (1 student) and pair (2 students) setups
 *   - Midterm + final defense periods with locked rubrics
 *   - One defense attempt per (team, period) with 3 assigned reviewers
 *   - Submitted sample papers for each team's midterm attempt
 *   - A handful of submitted reviews so scoring can be verified
 *
 * Run via `php artisan db:seed --class=TestingSeeder`. Idempotent: re-running
 * will not duplicate users, teams, attempts, or papers.
 */
class TestingSeeder extends Seeder
{
    public function run(): void
    {
        AppSetting::set('school_email_domain', 'example.com');

        $admin = $this->seedUser('admin@example.com', 'Admin', 'admin');
        $teachers = $this->seedTeachers();
        $students = $this->seedStudents();

        $subject = $this->seedSubject($teachers[1]);
        $this->enrollStudents($subject, $students);
        $this->attachReviewers($subject, $teachers);

        $periods = $this->seedDefensePeriods($subject);
        $rubrics = $this->seedRubrics($subject, $periods);
        $teams = $this->seedTeams($subject, $students);
        $attempts = $this->seedAttempts($teams, $periods);
        $this->assignAttemptReviewers($attempts, $teachers);
        $this->seedPapers($subject, $teams, $attempts);
        $this->seedSampleReviews($attempts, $rubrics);
    }

    /** @return array<int, User> teachers keyed 1..10 */
    private function seedTeachers(): array
    {
        $teachers = [];
        for ($i = 1; $i <= 10; $i++) {
            $teachers[$i] = $this->seedUser("teacher{$i}@example.com", "Teacher {$i}", 'teacher');
        }

        return $teachers;
    }

    /** @return array<int, User> students keyed 1..30 */
    private function seedStudents(): array
    {
        $students = [];
        for ($i = 1; $i <= 30; $i++) {
            $students[$i] = $this->seedUser("student{$i}@example.com", "Student {$i}", 'student');
        }

        return $students;
    }

    private function seedUser(string $email, string $name, string $role): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => $role,
                'status' => 'approved',
                'is_blocked' => false,
            ],
        );
    }

    private function seedSubject(User $teacher): Subject
    {
        return Subject::updateOrCreate(
            ['title' => 'FYP Capstone 2026'],
            [
                'description' => 'Final Year Project capstone subject used for acceptance testing.',
                'teacher_id' => $teacher->id,
                'passing_score' => 50,
                'join_code' => 'FYPSTU',
                'reviewer_code' => 'FYPREV',
                'require_approval' => false,
            ],
        );
    }

    /** @param array<int, User> $students */
    private function enrollStudents(Subject $subject, array $students): void
    {
        foreach ($students as $student) {
            SubjectMember::updateOrCreate(
                ['subject_id' => $subject->id, 'user_id' => $student->id],
                ['role' => 'student', 'status' => 'approved', 'role_label' => null],
            );
        }
    }

    /** @param array<int, User> $teachers */
    private function attachReviewers(Subject $subject, array $teachers): void
    {
        $assignments = [
            2 => ['role' => 'fyp_instructor', 'label' => 'FYP Instructor'],
            3 => ['role' => 'advisor', 'label' => 'Advisor'],
            4 => ['role' => 'advisor', 'label' => 'Advisor'],
            5 => ['role' => 'guest_panel', 'label' => 'Guest Panel'],
            6 => ['role' => 'guest_panel', 'label' => 'Guest Panel'],
            7 => ['role' => 'advisor', 'label' => 'Advisor'],
        ];

        foreach ($assignments as $idx => $info) {
            SubjectMember::updateOrCreate(
                ['subject_id' => $subject->id, 'user_id' => $teachers[$idx]->id],
                ['role' => $info['role'], 'status' => 'approved', 'role_label' => $info['label']],
            );
        }
    }

    /** @return array<string, DefensePeriod> */
    private function seedDefensePeriods(Subject $subject): array
    {
        $periods = [
            'midterm' => ['name' => 'Midterm Defense', 'sequence' => 1],
            'final' => ['name' => 'Final Defense', 'sequence' => 2],
        ];

        $out = [];
        foreach ($periods as $type => $info) {
            $out[$type] = DefensePeriod::firstOrCreate(
                ['subject_id' => $subject->id, 'type' => $type],
                [
                    'name' => $info['name'],
                    'sequence' => $info['sequence'],
                    'score_scale' => 'points_100',
                    'passing_score' => 50,
                    'status' => 'active',
                ],
            );
        }

        return $out;
    }

    /**
     * @param  array<string, DefensePeriod>  $periods
     * @return array<string, Rubric>
     */
    private function seedRubrics(Subject $subject, array $periods): array
    {
        $structure = [
            ['criteria' => 'Content Quality', 'max_score' => 50, 'weight' => 50],
            ['criteria' => 'Presentation Quality', 'max_score' => 50, 'weight' => 50],
        ];

        $out = [];
        foreach ($periods as $type => $period) {
            $out[$type] = Rubric::updateOrCreate(
                ['subject_id' => $subject->id, 'defense_period_id' => $period->id],
                [
                    'pdf_path' => 'rubrics/test-'.$type.'.pdf',
                    'structure_json' => $structure,
                    'status' => 'locked',
                ],
            );
        }

        return $out;
    }

    /**
     * Build 18 teams that exercise both solo and pair setups using all 30 students.
     *
     *   - Teams 1..6  → solo  (students 1..6)
     *   - Teams 7..18 → pair  (students 7..30, two per team)
     *
     * @param  array<int, User>  $students
     * @return array<int, Team>
     */
    private function seedTeams(Subject $subject, array $students): array
    {
        $teams = [];

        for ($i = 1; $i <= 6; $i++) {
            $team = $this->upsertTeam($subject, "Team Solo {$i}", $i);
            $this->setTeamMembers($team, [$students[$i]]);
            $teams[$i] = $team;
        }

        $cursor = 7;
        for ($i = 7; $i <= 18; $i++) {
            $team = $this->upsertTeam($subject, "Team Pair {$i}", $i);
            $this->setTeamMembers($team, [$students[$cursor], $students[$cursor + 1]]);
            $cursor += 2;
            $teams[$i] = $team;
        }

        return $teams;
    }

    private function upsertTeam(Subject $subject, string $name, int $index): Team
    {
        return Team::updateOrCreate(
            ['subject_id' => $subject->id, 'name' => $name],
            [
                'defense_date' => now()->addDays(7 + $index)->toDateString(),
                'defense_time' => sprintf('%02d:00', 8 + ($index % 8)),
                'defense_duration' => 30,
                'defense_room' => 'Room '.(300 + $index),
            ],
        );
    }

    /** @param array<int, User> $members */
    private function setTeamMembers(Team $team, array $members): void
    {
        $ids = array_map(fn (User $u) => $u->id, $members);
        $team->members()->sync($ids);
    }

    /**
     * @param  array<int, Team>  $teams
     * @param  array<string, DefensePeriod>  $periods
     * @return array<string, array<int, DefenseAttempt>> keyed [period_type][team_id]
     */
    private function seedAttempts(array $teams, array $periods): array
    {
        $out = ['midterm' => [], 'final' => []];

        foreach ($teams as $team) {
            foreach ($periods as $type => $period) {
                $attempt = DefenseAttempt::firstOrCreate(
                    [
                        'team_id' => $team->id,
                        'defense_period_id' => $period->id,
                        'attempt_number' => 1,
                    ],
                    [
                        'label' => 'Attempt 1',
                        'attempt_type' => 'regular',
                        'defense_date' => $team->defense_date,
                        'defense_time' => $team->defense_time,
                        'defense_duration' => $team->defense_duration,
                        'defense_room' => $team->defense_room,
                        'status' => 'scheduled',
                    ],
                );
                $out[$type][$team->id] = $attempt;
            }
        }

        return $out;
    }

    /**
     * Assign 3 reviewers per attempt: the FYP instructor + two advisors,
     * rotating advisors so different teams get different panels.
     *
     * Also attaches the reviewers to the team so they can see papers (mirrors
     * what TeamController@addMember does after a reviewer request is approved).
     *
     * @param  array<string, array<int, DefenseAttempt>>  $attempts
     * @param  array<int, User>  $teachers
     */
    private function assignAttemptReviewers(array $attempts, array $teachers): void
    {
        $advisorPool = [3, 4, 7];
        $panelPool = [5, 6];

        foreach ($attempts as $byTeam) {
            $i = 0;
            foreach ($byTeam as $attempt) {
                $reviewerIds = [
                    $teachers[2]->id,
                    $teachers[$advisorPool[$i % count($advisorPool)]]->id,
                    $teachers[$panelPool[$i % count($panelPool)]]->id,
                ];

                $roles = [
                    $teachers[2]->id => 'fyp_instructor',
                    $teachers[$advisorPool[$i % count($advisorPool)]]->id => 'advisor',
                    $teachers[$panelPool[$i % count($panelPool)]]->id => 'guest_panel',
                ];

                foreach ($reviewerIds as $reviewerId) {
                    DefenseAttemptReviewer::updateOrCreate(
                        [
                            'defense_attempt_id' => $attempt->id,
                            'reviewer_id' => $reviewerId,
                        ],
                        [
                            'committee_role' => $roles[$reviewerId],
                            'status' => 'active',
                            'excluded_from_calculation' => false,
                        ],
                    );

                    TeamMember::firstOrCreate([
                        'team_id' => $attempt->team_id,
                        'user_id' => $reviewerId,
                    ]);
                }

                $i++;
            }
        }
    }

    /**
     * @param  array<int, Team>  $teams
     * @param  array<string, array<int, DefenseAttempt>>  $attempts
     */
    private function seedPapers(Subject $subject, array $teams, array $attempts): void
    {
        foreach ($teams as $team) {
            foreach (['midterm', 'final'] as $type) {
                $attempt = $attempts[$type][$team->id] ?? null;
                if ($attempt === null) {
                    continue;
                }
                Paper::updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'defense_attempt_id' => $attempt->id,
                    ],
                    [
                        'subject_id' => $subject->id,
                        'file_path' => 'papers/test-team-'.$team->id.'-'.$type.'.pdf',
                        'visibility_status' => $type === 'midterm' ? 'submitted' : 'draft',
                    ],
                );
            }
        }
    }

    /**
     * Submit one review per midterm attempt from the FYP instructor (teacher2)
     * so the scoring/total calculation step in the acceptance test has data to
     * work with. Other reviewers stay unsubmitted to exercise partial state.
     *
     * @param  array<string, array<int, DefenseAttempt>>  $attempts
     * @param  array<string, Rubric>  $rubrics
     */
    private function seedSampleReviews(array $attempts, array $rubrics): void
    {
        foreach ($attempts['midterm'] as $attempt) {
            $assignment = DefenseAttemptReviewer::where('defense_attempt_id', $attempt->id)
                ->where('committee_role', 'fyp_instructor')
                ->first();
            if ($assignment === null) {
                continue;
            }

            $paper = Paper::where('defense_attempt_id', $attempt->id)->first();
            if ($paper === null) {
                continue;
            }

            Review::updateOrCreate(
                [
                    'paper_id' => $paper->id,
                    'defense_attempt_reviewer_id' => $assignment->id,
                ],
                [
                    'defense_attempt_id' => $attempt->id,
                    'reviewer_id' => $assignment->reviewer_id,
                    'committee_role' => 'fyp_instructor',
                    'scores_json' => [
                        ['criteria' => 'Content Quality', 'score' => 42, 'max_score' => 50, 'weight' => 50, 'comment' => null],
                        ['criteria' => 'Presentation Quality', 'score' => 38, 'max_score' => 50, 'weight' => 50, 'comment' => null],
                    ],
                    'comment' => 'Solid first draft. Tighten the methodology section before final defense.',
                    'is_submitted' => true,
                    'locked_at' => now(),
                ],
            );
        }
    }
}
