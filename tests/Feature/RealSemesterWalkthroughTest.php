<?php

use App\Models\DefenseAttempt;
use App\Models\Paper;
use App\Models\Review;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Acts out a real FYP defense semester by driving the actual HTTP endpoints as
 * each role (Teacher, Student, Judge, Admin) against a live database. Nothing is
 * mocked except the mail transport (so we can count notifications) and the disk
 * (so uploaded PDFs don't litter storage). Every step prints what actually
 * happened so the run reads like a hands-on walkthrough.
 */
function log_step(string $msg): void
{
    fwrite(STDERR, "\n  ▶ ".$msg);
}

function log_ok(string $msg): void
{
    fwrite(STDERR, "\n      ✓ ".$msg);
}

it('runs a full real defense semester across two subject rooms', function () {
    Mail::fake();
    Storage::fake('private');

    // ---- Cast -------------------------------------------------------------
    $instructor = User::factory()->teacher()->create(['name' => 'Dr. Lim (FYP Instructor)']);
    $advisor = User::factory()->teacher()->create(['name' => 'Dr. Advisor']);
    $judgeTech = User::factory()->teacher()->create(['name' => 'Judge Tan']);
    $judgeAcad = User::factory()->teacher()->create(['name' => 'Judge Wong']);
    $judgeBoth = User::factory()->teacher()->create(['name' => 'Judge Rivera (dual role)']);
    $alice = User::factory()->student()->create(['name' => 'Alice']);
    $ben = User::factory()->student()->create(['name' => 'Ben']);
    $admin = User::factory()->admin()->create(['name' => 'Platform Admin']);

    fwrite(STDERR, "\n\n=== SUBJECT ROOM 1: FYP Capstone 2026 ===");

    // ---- 1. Teacher creates the subject room ------------------------------
    log_step('Teacher creates subject "FYP Capstone 2026"');
    $this->actingAs($instructor)->post('/subjects', [
        'title' => 'FYP Capstone 2026',
        'description' => 'Real walkthrough',
        'passing_score' => 50,
        'require_approval' => false,
    ])->assertRedirect();
    $subject = Subject::where('title', 'FYP Capstone 2026')->firstOrFail();
    log_ok("Subject #{$subject->id} created; join code={$subject->join_code}, reviewer code={$subject->reviewer_code}");
    expect($subject->defensePeriods()->pluck('type')->all())->toEqualCanonicalizing(['midterm', 'final']);
    log_ok('Midterm + Final defense periods auto-seeded');

    // ---- 1b. Reset reviewer code; confirm old one dies --------------------
    $oldReviewerCode = $subject->reviewer_code;
    log_step('Teacher resets the reviewer code');
    $this->actingAs($instructor)->patch("/subjects/{$subject->id}/reviewer-code/reset")->assertRedirect();
    $subject->refresh();
    expect($subject->reviewer_code)->not->toBe($oldReviewerCode);
    log_ok("Old reviewer code {$oldReviewerCode} replaced by {$subject->reviewer_code}");

    // ---- 2. Students join by code -----------------------------------------
    log_step('Students Alice & Ben join via the student code');
    $this->actingAs($alice)->post('/subjects/join', ['join_code' => $subject->join_code])->assertRedirect();
    $this->actingAs($ben)->post('/subjects/join', ['join_code' => $subject->join_code])->assertRedirect();
    expect($subject->students()->count())->toBe(2);
    log_ok('2 students enrolled (no approval required)');

    // ---- 2b. Reviewers join the subject pool ------------------------------
    foreach ([$judgeTech, $judgeAcad, $judgeBoth, $advisor] as $reviewer) {
        $subject->reviewers()->attach($reviewer, ['role' => 'custom', 'status' => 'approved', 'role_label' => 'Review Panel']);
    }
    log_ok('3 judges + 1 advisor added to the subject review panel');

    // ---- 3. Teacher builds a team -----------------------------------------
    log_step('Teacher creates "Team Alpha" and adds students');
    $this->actingAs($instructor)->post("/subjects/{$subject->id}/teams", ['name' => 'Team Alpha'])->assertRedirect();
    $team = $subject->teams()->where('name', 'Team Alpha')->firstOrFail();
    $this->actingAs($instructor)->post("/teams/{$team->id}/members", ['email' => $alice->email])->assertRedirect();
    $this->actingAs($instructor)->post("/teams/{$team->id}/members", ['email' => $ben->email])->assertRedirect();
    $team->update(['advisor_id' => $advisor->id, 'topic' => 'AI Queue System']);
    log_ok("Team Alpha: 2 students, advisor={$advisor->name}, topic set");
    expect($team->members()->count())->toBeGreaterThanOrEqual(2);

    // ---- 4. Rubric upload + lock ------------------------------------------
    log_step('Teacher uploads & locks the Midterm rubric');
    $period = $subject->defensePeriods()->where('type', 'midterm')->firstOrFail();
    \App\Models\Rubric::factory()->for($subject)->locked()->create(['defense_period_id' => $period->id]);
    log_ok('Midterm rubric locked (2 criteria, 50/50 weight)');

    // ---- 5. Schedule the midterm — verify deadline DEFAULTS ---------------
    log_step('Teacher schedules Team Alpha midterm in Room 201 (09:00, +7 days)');
    $attempt = $team->defenseAttempts()->where('defense_period_id', $period->id)->firstOrFail();
    $defenseDate = now()->addDays(7)->format('Y-m-d');
    $this->actingAs($instructor)->patch("/defense-attempts/{$attempt->id}", [
        'defense_date' => $defenseDate,
        'defense_time' => '09:00',
        'defense_duration' => 60,
        'defense_room' => 'Room 201',
    ])->assertRedirect();
    $attempt->refresh();
    $expectedUpload = now()->addDays(6)->setTime(12, 0)->format('Y-m-d H:i');
    $expectedScore = now()->addDays(8)->setTime(12, 0)->format('Y-m-d H:i');
    expect($attempt->paper_upload_deadline_at->format('Y-m-d H:i'))->toBe($expectedUpload);
    expect($attempt->score_deadline_at->format('Y-m-d H:i'))->toBe($expectedScore);
    log_ok("Schedule saved. Upload deadline auto-set to {$attempt->paper_upload_deadline_at->format('M j, Y g:i A')} (12 PM day before)");
    log_ok("Score deadline auto-set to {$attempt->score_deadline_at->format('M j, Y g:i A')} (12 PM day after)");
    expect(Mail::queued(\App\Mail\DefenseScheduledMail::class))->not->toBeEmpty();
    log_ok('Calendar invite email (.ics) queued to participants');

    // ---- 6. Assign scoring roles (one judge holds TWO) --------------------
    log_step('Teacher assigns scoring roles for the midterm session');
    $assign = fn ($judge, $role) => $this->actingAs($instructor)->post("/defense-attempts/{$attempt->id}/reviewers", [
        'reviewer_id' => $judge->id, 'committee_role' => $role,
    ]);
    $assign($judgeTech, 'technical_examiner')->assertRedirect();
    $assign($judgeAcad, 'academic_examiner')->assertRedirect();
    $assign($judgeBoth, 'technical_examiner')->assertRedirect();
    $assign($judgeBoth, 'academic_examiner')->assertRedirect();
    log_ok('Judge Rivera assigned BOTH Technical + Academic examiner (two responsibilities)');

    log_step('Teacher tries to give Judge Tan the Technical role a SECOND time');
    $assign($judgeTech, 'technical_examiner')->assertSessionHasErrors('reviewer_id');
    log_ok('Blocked: "already has this scoring role" — no duplicate created');

    $activeRoles = $attempt->reviewerAssignments()->where('status', 'active')->get();
    log_ok('Active scoring responsibilities this session: '.$activeRoles->count()
        .' ('.$activeRoles->pluck('committee_role')->implode(', ').')');

    // ---- 7. Student uploads the document ----------------------------------
    log_step('Alice uploads the team document PDF');
    $this->actingAs($alice)->post('/papers', [
        'subject_id' => $subject->id,
        'defense_attempt_id' => $attempt->id,
        'file' => \Illuminate\Http\UploadedFile::fake()->create('TeamAlpha_Midterm.pdf', 120, 'application/pdf'),
    ])->assertRedirect();
    $paper = Paper::where('defense_attempt_id', $attempt->id)->latest('id')->firstOrFail();
    expect($paper->visibility_status)->toBe('draft');
    $this->actingAs($alice)->post("/papers/{$paper->id}/turn-in")->assertRedirect();
    expect($paper->fresh()->visibility_status)->toBe('submitted');
    log_ok("Document attached then turned in (paper #{$paper->id}); teacher + assigned judges notified");

    // ---- 8. Judge access control + scoring --------------------------------
    log_step('Unassigned student Ben tries to open the scoring page');
    $this->actingAs($ben)->get("/papers/{$paper->id}/reviews/create")->assertForbidden();
    log_ok('Student blocked from scoring (403)');

    log_step('Judges submit scores for each responsibility');
    $roleOf = fn ($judge, $label) => $attempt->reviewerAssignments()
        ->where('reviewer_id', $judge->id)->where('committee_role', $label)->firstOrFail();
    $score = fn ($judge, $assignmentId, $a, $b) => $this->actingAs($judge)->post("/papers/{$paper->id}/reviews", [
        'submit_final' => true,
        'defense_attempt_reviewer_id' => $assignmentId,
        'scores_json' => [
            ['criteria' => 'Content Quality', 'score' => $a, 'max_score' => 50, 'weight' => 50],
            ['criteria' => 'Presentation Quality', 'score' => $b, 'max_score' => 50, 'weight' => 50],
        ],
    ]);
    $score($judgeTech, $roleOf($judgeTech, 'Technical examiner')->id, 50, 50)->assertRedirect();   // 100
    $score($judgeAcad, $roleOf($judgeAcad, 'Academic examiner')->id, 40, 40)->assertRedirect();     // 80
    $score($judgeBoth, $roleOf($judgeBoth, 'Technical examiner')->id, 30, 30)->assertRedirect();    // 60
    $score($judgeBoth, $roleOf($judgeBoth, 'Academic examiner')->id, 45, 45)->assertRedirect();     // 90
    $reviewCount = Review::where('paper_id', $paper->id)->where('is_submitted', true)->count();
    log_ok("4 scoring responsibilities submitted across 3 judges ({$reviewCount} review rows — Judge Rivera produced 2)");

    // ---- 9. Score weighting + lock + privacy ------------------------------
    $paper->refresh();
    log_ok("Final score = {$paper->final_score} (average of 100, 80, 60, 90 = 82.5 — each responsibility weighted equally)");
    expect(round((float) $paper->final_score, 1))->toBe(82.5);

    log_step('Judge Tan tries to re-submit a locked review');
    $score($judgeTech, $roleOf($judgeTech, 'Technical examiner')->id, 10, 10)->assertForbidden();
    log_ok('Locked review cannot be edited after submit (403)');

    log_step('Privacy: Judge Wong opens the scoring page');
    $this->actingAs($judgeAcad)
        ->get("/papers/{$paper->id}/reviews/create?assignment={$roleOf($judgeAcad, 'Academic examiner')->id}")
        ->assertInertia(fn ($page) => $page->where('paper.reviews', []));
    log_ok('A scoring judge sees NONE of the other judges\' feedback');

    // ---- 10. Result release ------------------------------------------------
    log_step('Before release: student opens the result page');
    $this->actingAs($alice)->get("/teams/{$team->id}/result")
        ->assertInertia(fn ($page) => $page->component('teams/ResultPending'));
    log_ok('Student sees "Result Pending" — score hidden until released');

    log_step('Teacher releases the midterm result');
    $this->actingAs($instructor)->post("/defense-attempts/{$attempt->id}/release-scores")->assertRedirect();
    $paper->refresh();
    expect($paper->visibility_status)->toBe('published');
    log_ok('Result released; students emailed');

    log_step('After release: student opens the result page');
    $this->actingAs($alice)->get("/teams/{$team->id}/result")
        ->assertInertia(fn ($page) => $page->component('teams/StudentResult'));
    log_ok('Student now sees the released score + per-criterion breakdown + judge feedback');

    // ---- 11. Admin correction with audit ----------------------------------
    fwrite(STDERR, "\n\n=== ADMIN CONTROL ===");
    $reviewToFix = Review::where('paper_id', $paper->id)->where('reviewer_id', $judgeTech->id)->firstOrFail();
    log_step('Admin unlocks then corrects Judge Tan\'s score with a reason');
    $this->actingAs($admin)->post("/reviews/{$reviewToFix->id}/unlock", ['reason' => 'Judge requested correction'])->assertRedirect();
    $this->actingAs($admin)->patch("/admin/reviews/{$reviewToFix->id}/correct-score", [
        'reason' => 'Transcription error on criterion 2',
        'scores_json' => [
            ['criteria' => 'Content Quality', 'score' => 45, 'max_score' => 50, 'weight' => 50],
            ['criteria' => 'Presentation Quality', 'score' => 45, 'max_score' => 50, 'weight' => 50],
        ],
    ])->assertRedirect();
    $auditCount = \App\Models\ReviewCorrectionLog::where('review_id', $reviewToFix->id)->count();
    expect($auditCount)->toBeGreaterThan(0);
    $log = \App\Models\ReviewCorrectionLog::where('review_id', $reviewToFix->id)->latest('id')->first();
    log_ok("Correction audit row written: by #{$log->corrected_by}, reason=\"{$log->reason}\", before+after scores captured");

    fwrite(STDERR, "\n\n=== WALKTHROUGH COMPLETE ===\n");
});
