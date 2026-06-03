<?php

use App\Models\DefenseAttempt;
use App\Models\DefenseAttemptReviewer;
use App\Models\DefensePeriod;
use App\Models\Paper;
use App\Models\Review;
use App\Models\Rubric;
use App\Models\Subject;
use App\Models\SubjectMember;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\TestingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * End-to-end Scormetry 2.0 acceptance test exercising every numbered item in
 * the test brief. Each `it(...)` block maps to one or more brief items and is
 * labelled in the test name (e.g. "[2]") for traceability.
 *
 * All blocks run against the fixture set produced by `TestingSeeder`, which is
 * re-seeded under LazilyRefreshDatabase. That seeder is the same one shipped
 * for manual QA against the dev DB, so this test verifies it in addition to
 * the application behaviour.
 */
beforeEach(function () {
    Mail::fake();
    Storage::fake('private');
    $this->seed(TestingSeeder::class);
});

/* ------------------------------------------------------------------ *
 *  [1][2]  Account creation + login                                  *
 * ------------------------------------------------------------------ */

it('[1] seeds all 30 student and 10 teacher accounts with the correct credentials', function () {
    expect(User::where('role', 'student')->count())->toBe(30);
    expect(User::where('role', 'teacher')->count())->toBe(10);
    expect(User::where('role', 'admin')->count())->toBe(1);

    foreach (range(1, 30) as $i) {
        $student = User::where('email', "student{$i}@example.com")->first();
        expect($student)->not->toBeNull();
        expect($student->role)->toBe('student');
        expect($student->status)->toBe('approved');
        expect(Hash::check('password', $student->password))->toBeTrue();
    }

    foreach (range(1, 10) as $i) {
        $teacher = User::where('email', "teacher{$i}@example.com")->first();
        expect($teacher)->not->toBeNull();
        expect($teacher->role)->toBe('teacher');
        expect($teacher->status)->toBe('approved');
        expect(Hash::check('password', $teacher->password))->toBeTrue();
    }
});

it('[2] lets all 40 dummy accounts log in via HTTP and reach the dashboard', function () {
    $emails = collect();
    foreach (range(1, 30) as $i) {
        $emails->push("student{$i}@example.com");
    }
    foreach (range(1, 10) as $i) {
        $emails->push("teacher{$i}@example.com");
    }

    foreach ($emails as $email) {
        $response = $this->post('/login', ['email' => $email, 'password' => 'password']);
        $response->assertRedirect('/dashboard');

        $this->get('/dashboard')->assertOk();
        $this->post('/logout');
    }
});

/* ------------------------------------------------------------------ *
 *  [3][4]  Role-based access control on common routes                *
 * ------------------------------------------------------------------ */

it('[3] keeps students out of teacher- and admin-only routes', function () {
    $student = User::where('email', 'student1@example.com')->firstOrFail();

    $this->actingAs($student)->get('/admin/dashboard')->assertForbidden();
    $this->actingAs($student)->get('/admin/users')->assertForbidden();
    $this->actingAs($student)->get('/admin/classrooms')->assertForbidden();
    $this->actingAs($student)->get('/admin/settings')->assertForbidden();

    $subject = Subject::where('title', 'FYP Capstone 2026')->firstOrFail();
    $this->actingAs($student)
        ->post("/subjects/{$subject->id}/reviewers", [
            'email' => 'teacher8@example.com',
            'committee_role' => 'advisor',
        ])
        ->assertForbidden();

    // Subject creation POST is correctly gated by StoreSubjectRequest::authorize()
    $this->actingAs($student)
        ->post('/subjects', [
            'title' => 'Should Fail',
            'description' => '',
            'passing_score' => 50,
            'require_approval' => false,
        ])
        ->assertForbidden();
});

it('[3][BUG] GET /subjects/create leaks the teacher form page to students', function () {
    // Finding for the report. SubjectController@create has no authorization gate so
    // any authenticated user can render `subjects/Create`. The POST is properly
    // blocked, so this is a UX/disclosure issue rather than a privilege escalation,
    // but the sidebar gates teacher actions on role and this controller should match.
    $student = User::where('email', 'student1@example.com')->firstOrFail();
    $this->actingAs($student)->get('/subjects/create')->assertOk(); // current — should be 403
})->todo('SubjectController@create needs an isTeacher/isAdmin gate to match StoreSubjectRequest.');

it('[4] lets teachers reach teacher features but blocks them from admin routes', function () {
    $teacher = User::where('email', 'teacher1@example.com')->firstOrFail();

    $this->actingAs($teacher)->get('/teams')->assertOk();
    $this->actingAs($teacher)->get('/subjects')->assertOk();
    $this->actingAs($teacher)->get('/subjects/create')->assertOk();

    $this->actingAs($teacher)->get('/admin/dashboard')->assertForbidden();
    $this->actingAs($teacher)->get('/admin/users')->assertForbidden();
});

/* ------------------------------------------------------------------ *
 *  [5][6]  Subject creation + reviewer assignment                    *
 * ------------------------------------------------------------------ */

it('[5][6] lets a teacher create a subject and assign teachers as reviewers', function () {
    $owner = User::where('email', 'teacher1@example.com')->firstOrFail();

    $this->actingAs($owner)
        ->post('/subjects', [
            'title' => 'FYP Pilot Class',
            'description' => 'For workflow regression.',
            'passing_score' => 60,
            'require_approval' => false,
        ])
        ->assertRedirect();

    $subject = Subject::where('title', 'FYP Pilot Class')->firstOrFail();
    expect($subject->teacher_id)->toBe($owner->id);
    expect($subject->defensePeriods()->pluck('type')->all())->toEqualCanonicalizing(['midterm', 'final']);

    foreach (['teacher2', 'teacher3', 'teacher5'] as $email) {
        $this->actingAs($owner)
            ->post("/subjects/{$subject->id}/reviewers", [
                'email' => "{$email}@example.com",
                'committee_role' => $email === 'teacher2' ? 'fyp_instructor' : 'advisor',
            ])
            ->assertRedirect();
    }

    expect($subject->reviewers()->count())->toBe(3);
});

/* ------------------------------------------------------------------ *
 *  [7][8]  Team creation - solo and pair setups                      *
 * ------------------------------------------------------------------ */

it('[7][8] supports both solo and pair team configurations', function () {
    $subject = Subject::where('title', 'FYP Capstone 2026')->firstOrFail();

    $soloTeam = Team::where('subject_id', $subject->id)->where('name', 'Team Solo 1')->firstOrFail();
    $pairTeam = Team::where('subject_id', $subject->id)->where('name', 'Team Pair 7')->firstOrFail();

    expect($soloTeam->members()->where('users.role', 'student')->count())->toBe(1);
    expect($pairTeam->members()->where('users.role', 'student')->count())->toBe(2);

    // Owner can create another team via the controller.
    $owner = User::where('email', 'teacher1@example.com')->firstOrFail();
    $this->actingAs($owner)
        ->post("/subjects/{$subject->id}/teams", ['name' => 'Team Mid-Session 99'])
        ->assertRedirect();

    $newTeam = Team::where('subject_id', $subject->id)->where('name', 'Team Mid-Session 99')->firstOrFail();
    expect($newTeam->defenseAttempts()->count())->toBe(2);
});

/* ------------------------------------------------------------------ *
 *  [9]  Defense scheduling                                           *
 * ------------------------------------------------------------------ */

it('[9] schedules a defense including date, time, room and panel assignments', function () {
    $owner = User::where('email', 'teacher1@example.com')->firstOrFail();
    $team = Team::where('name', 'Team Solo 1')->firstOrFail();

    $this->actingAs($owner)
        ->patch("/teams/{$team->id}/schedule", [
            'defense_date' => '2026-07-15',
            'defense_time' => '14:30',
            'defense_duration' => 45,
            'defense_room' => 'Auditorium A',
        ])
        ->assertRedirect();

    $team->refresh();
    expect($team->defense_date->toDateString())->toBe('2026-07-15');
    expect((string) $team->defense_time)->toContain('14:30');
    expect($team->defense_room)->toBe('Auditorium A');

    $attempt = $team->defenseAttempts()->orderBy('id')->first();
    expect($attempt->status)->toBe('scheduled');
    expect($attempt->defense_room)->toBe('Auditorium A');

    expect($team->members()->where('users.role', 'teacher')->count())->toBeGreaterThanOrEqual(3);
});

/* ------------------------------------------------------------------ *
 *  [10] Sample document upload                                       *
 * ------------------------------------------------------------------ */

it('[10] lets a student upload their report PDF for their team attempt', function () {
    $student = User::where('email', 'student1@example.com')->firstOrFail();
    $subject = Subject::where('title', 'FYP Capstone 2026')->firstOrFail();
    $team = Team::where('name', 'Team Solo 1')->firstOrFail();
    $midtermAttempt = $team->defenseAttempts()
        ->whereHas('period', fn ($q) => $q->where('type', 'final'))
        ->firstOrFail();

    $file = UploadedFile::fake()->create('FYP_Report_v1.pdf', 100, 'application/pdf');

    $this->actingAs($student)
        ->post('/papers', [
            'subject_id' => $subject->id,
            'defense_attempt_id' => $midtermAttempt->id,
            'file' => $file,
        ])
        ->assertRedirect('/papers');

    $paper = Paper::where('team_id', $team->id)
        ->where('defense_attempt_id', $midtermAttempt->id)
        ->latest('id')
        ->firstOrFail();

    expect($paper->visibility_status)->toBe('submitted');
    Storage::disk('private')->assertExists($paper->file_path);
});

/* ------------------------------------------------------------------ *
 *  [11][12] Document access scoping                                  *
 * ------------------------------------------------------------------ */

it('[11] lets an assigned reviewer access papers for their assigned team', function () {
    $reviewer = User::where('email', 'teacher2@example.com')->firstOrFail();
    $paper = Paper::query()
        ->where('visibility_status', 'submitted')
        ->whereHas('defenseAttempt.reviewerAssignments', fn ($q) => $q->where('reviewer_id', $reviewer->id)->where('status', 'active'))
        ->firstOrFail();

    $this->actingAs($reviewer)
        ->get("/papers/{$paper->id}")
        ->assertOk();
});

it('[12] prevents a student from seeing another team\'s paper', function () {
    $student1 = User::where('email', 'student1@example.com')->firstOrFail();
    $otherTeam = Team::where('name', 'Team Pair 7')->firstOrFail();
    $otherPaper = Paper::where('team_id', $otherTeam->id)
        ->where('visibility_status', 'submitted')
        ->firstOrFail();

    $this->actingAs($student1)
        ->get("/papers/{$otherPaper->id}")
        ->assertForbidden();
});

/* ------------------------------------------------------------------ *
 *  [13][14] Update changes (schedule, room, panel, document)         *
 * ------------------------------------------------------------------ */

it('[13][14] propagates schedule, room, panel and document changes', function () {
    $owner = User::where('email', 'teacher1@example.com')->firstOrFail();
    $team = Team::where('name', 'Team Solo 2')->firstOrFail();

    // Change date/time/room
    $this->actingAs($owner)
        ->patch("/teams/{$team->id}/schedule", [
            'defense_date' => '2026-08-20',
            'defense_time' => '10:15',
            'defense_duration' => 60,
            'defense_room' => 'Lab 12',
        ])
        ->assertRedirect();

    $team->refresh();
    expect($team->defense_date->toDateString())->toBe('2026-08-20');
    expect($team->defense_room)->toBe('Lab 12');

    // Swap a panel member: remove teacher6, add teacher8 as guest_panel
    $subject = $team->subject;
    SubjectMember::updateOrCreate(
        ['subject_id' => $subject->id, 'user_id' => User::where('email', 'teacher8@example.com')->value('id')],
        ['role' => 'guest_panel', 'status' => 'approved', 'role_label' => 'Guest Panel'],
    );
    $teacher6 = User::where('email', 'teacher6@example.com')->firstOrFail();
    $teacher8 = User::where('email', 'teacher8@example.com')->firstOrFail();

    $this->actingAs($owner)
        ->delete("/teams/{$team->id}/members/{$teacher6->id}")
        ->assertRedirect();
    $this->actingAs($owner)
        ->post("/teams/{$team->id}/members", ['email' => $teacher8->email])
        ->assertRedirect();

    expect($team->members()->where('users.id', $teacher8->id)->exists())->toBeTrue();

    // Replace the document
    $student = $team->members()->where('users.role', 'student')->firstOrFail();
    $file = UploadedFile::fake()->create('Report_v2.pdf', 80, 'application/pdf');

    $attempt = $team->defenseAttempts()->orderBy('id')->first();
    $this->actingAs($student)
        ->post('/papers', [
            'subject_id' => $subject->id,
            'defense_attempt_id' => $attempt->id,
            'file' => $file,
        ])
        ->assertRedirect('/papers');

    $paper = Paper::where('team_id', $team->id)
        ->where('defense_attempt_id', $attempt->id)
        ->latest('id')
        ->firstOrFail();
    expect($paper->visibility_status)->toBe('submitted');
    Storage::disk('private')->assertExists($paper->file_path);
});

/* ------------------------------------------------------------------ *
 *  [15][16][17][18]  Scoring, calculation, feedback, persistence     *
 * ------------------------------------------------------------------ */

it('[15][16][17][18] records scores, calculates totals, saves comments under the right attempt', function () {
    $advisor = User::where('email', 'teacher3@example.com')->firstOrFail();
    $panel = User::where('email', 'teacher5@example.com')->firstOrFail();

    // Find an attempt where both teacher3 and teacher5 are assigned and a submitted paper exists.
    $attempt = DefenseAttempt::query()
        ->whereHas('reviewerAssignments', fn ($q) => $q->where('reviewer_id', $advisor->id)->where('status', 'active'))
        ->whereHas('reviewerAssignments', fn ($q) => $q->where('reviewer_id', $panel->id)->where('status', 'active'))
        ->whereHas('papers', fn ($q) => $q->where('visibility_status', 'submitted'))
        ->first();

    expect($attempt)->not->toBeNull('Need a midterm attempt with teacher3+teacher5 assigned for this scenario');

    $paper = $attempt->papers()->where('visibility_status', 'submitted')->latest('id')->firstOrFail();

    $this->actingAs($advisor)
        ->post("/papers/{$paper->id}/reviews", [
            'scores_json' => [
                ['criteria' => 'Content Quality', 'score' => 45],
                ['criteria' => 'Presentation Quality', 'score' => 40],
            ],
            'comment' => 'Strong technical depth; tighten the conclusion.',
        ])
        ->assertRedirect("/papers/{$paper->id}");

    $this->actingAs($panel)
        ->post("/papers/{$paper->id}/reviews", [
            'scores_json' => [
                ['criteria' => 'Content Quality', 'score' => 30],
                ['criteria' => 'Presentation Quality', 'score' => 35],
            ],
            'comment' => 'Good visuals; rehearse timing more carefully.',
        ])
        ->assertRedirect("/papers/{$paper->id}");

    $paper->refresh();
    $attempt->refresh();

    // Reviews persisted under the right paper + attempt
    $reviews = Review::where('paper_id', $paper->id)->where('is_submitted', true)->get();
    expect($reviews)->toHaveCount(3); // fyp_instructor seed + advisor + panel
    foreach ($reviews as $r) {
        expect($r->defense_attempt_id)->toBe($attempt->id);
        expect($r->defense_attempt_reviewer_id)->not->toBeNull();
    }

    // Feedback comments saved
    expect($reviews->pluck('comment')->filter()->count())->toBeGreaterThanOrEqual(2);

    // Final score recalculated and propagated to paper + attempt
    // Weights: Content 50, Presentation 50; rubric max_score 50 per criteria.
    // Per-review total (already weighted to /100): seed FYP instructor = (42/50*50)+(38/50*50)=80
    //                                            advisor               = (45/50*50)+(40/50*50)=85
    //                                            panel                 = (30/50*50)+(35/50*50)=65
    //                              average      = round((80+85+65)/3,2) = 76.67
    expect((float) $paper->final_score)->toBeGreaterThan(70.0);
    expect((float) $paper->final_score)->toBeLessThan(85.0);
    expect((float) $attempt->final_score)->toBe((float) $paper->final_score);
});

/* ------------------------------------------------------------------ *
 *  [19]  Cross-team isolation between students and teachers          *
 * ------------------------------------------------------------------ */

it('[19] keeps reviewers out of teams they were not assigned to', function () {
    $reviewer = User::where('email', 'teacher2@example.com')->firstOrFail();

    // Strip teacher2 off team "Team Pair 8" so we have a verifiably-unassigned team.
    $unassignedTeam = Team::where('name', 'Team Pair 8')->firstOrFail();
    foreach ($unassignedTeam->defenseAttempts as $attempt) {
        $attempt->reviewerAssignments()->where('reviewer_id', $reviewer->id)->delete();
    }
    $unassignedTeam->members()->detach($reviewer->id);

    $paper = Paper::where('team_id', $unassignedTeam->id)
        ->where('visibility_status', 'submitted')
        ->firstOrFail();

    $this->actingAs($reviewer)
        ->get("/papers/{$paper->id}")
        ->assertForbidden();
});

it('[19] keeps students from accessing another team\'s scoring / result pages', function () {
    $student1 = User::where('email', 'student1@example.com')->firstOrFail();
    $otherTeam = Team::where('name', 'Team Pair 9')->firstOrFail();

    $this->actingAs($student1)
        ->get("/teams/{$otherTeam->id}/result")
        ->assertForbidden();
});

/* ------------------------------------------------------------------ *
 *  Re-defense lifecycle: add + remove                                *
 * ------------------------------------------------------------------ */

it('allows the subject owner to add and remove an empty re-defense attempt', function () {
    $owner = User::where('email', 'teacher1@example.com')->firstOrFail();
    $team = Team::where('name', 'Team Solo 3')->firstOrFail();
    $period = DefensePeriod::where('subject_id', $team->subject_id)->where('type', 'midterm')->firstOrFail();

    $this->actingAs($owner)
        ->post("/defense-periods/{$period->id}/attempts", [
            'team_id' => $team->id,
            'attempt_type' => 're_defense',
        ])
        ->assertRedirect();

    $reDefense = DefenseAttempt::where('team_id', $team->id)
        ->where('defense_period_id', $period->id)
        ->where('attempt_type', 're_defense')
        ->latest('id')
        ->firstOrFail();

    $this->actingAs($owner)
        ->delete("/defense-attempts/{$reDefense->id}")
        ->assertRedirect();

    expect(DefenseAttempt::find($reDefense->id))->toBeNull();
});

it('refuses to delete the original (regular) attempt', function () {
    $owner = User::where('email', 'teacher1@example.com')->firstOrFail();
    $regular = DefenseAttempt::where('attempt_type', 'regular')->firstOrFail();

    $this->actingAs($owner)
        ->delete("/defense-attempts/{$regular->id}")
        ->assertRedirect();

    expect(DefenseAttempt::find($regular->id))->not->toBeNull();
});

it('refuses to delete a re-defense that already has a submitted review', function () {
    $owner = User::where('email', 'teacher1@example.com')->firstOrFail();
    $team = Team::where('name', 'Team Solo 4')->firstOrFail();
    $period = DefensePeriod::where('subject_id', $team->subject_id)->where('type', 'midterm')->firstOrFail();

    $this->actingAs($owner)
        ->post("/defense-periods/{$period->id}/attempts", [
            'team_id' => $team->id,
            'attempt_type' => 're_defense',
        ])
        ->assertRedirect();

    $reDefense = DefenseAttempt::where('team_id', $team->id)
        ->where('defense_period_id', $period->id)
        ->where('attempt_type', 're_defense')
        ->latest('id')
        ->firstOrFail();

    $paper = Paper::create([
        'team_id' => $team->id,
        'defense_attempt_id' => $reDefense->id,
        'subject_id' => $team->subject_id,
        'file_path' => 'papers/test-redefense.pdf',
        'visibility_status' => 'submitted',
    ]);

    Review::create([
        'paper_id' => $paper->id,
        'defense_attempt_id' => $reDefense->id,
        'reviewer_id' => User::where('email', 'teacher2@example.com')->value('id'),
        'committee_role' => 'fyp_instructor',
        'scores_json' => [['criteria' => 'Content Quality', 'score' => 40, 'max_score' => 50, 'weight' => 50, 'comment' => null]],
        'comment' => 'locked in',
        'is_submitted' => true,
        'locked_at' => now(),
    ]);

    $this->actingAs($owner)
        ->delete("/defense-attempts/{$reDefense->id}")
        ->assertRedirect();

    expect(DefenseAttempt::find($reDefense->id))->not->toBeNull();
});
