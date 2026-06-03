<?php

use App\Models\DefenseAttemptReviewer;
use App\Models\Subject;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

it('allows teacher to create a team for a subject', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->post("/subjects/{$subject->id}/teams", [
            'name' => 'Team Alpha',
            'topic' => 'Scormetry 2.0: AI Rubric Generation and Secure Document Management for Academic Evaluations',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'subject_id' => $subject->id,
        'name' => 'Team Alpha',
        'topic' => 'Scormetry 2.0: AI Rubric Generation and Secure Document Management for Academic Evaluations',
    ]);
});

it('automatically assigns the subject owner to every new team defense round', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->post("/subjects/{$subject->id}/teams", ['name' => 'Team Owner Required'])
        ->assertRedirect();

    $team = Team::where('subject_id', $subject->id)
        ->where('name', 'Team Owner Required')
        ->firstOrFail();
    $attempts = $team->defenseAttempts()->with('period')->get();

    expect($attempts)->toHaveCount(2);
    expect($team->members()->whereKey($teacher->id)->exists())->toBeTrue();

    foreach ($attempts as $attempt) {
        expect(DefenseAttemptReviewer::where('defense_attempt_id', $attempt->id)
            ->where('reviewer_id', $teacher->id)
            ->where('status', 'active')
            ->where('committee_role', 'fyp_instructor')
            ->count())->toBe(1);
    }
});

it('does not allow the subject owner to be removed from a defense round', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->post("/subjects/{$subject->id}/teams", ['name' => 'Team Owner Locked'])
        ->assertRedirect();

    $team = Team::where('subject_id', $subject->id)
        ->where('name', 'Team Owner Locked')
        ->firstOrFail();
    $attempt = $team->defenseAttempts()->firstOrFail();

    $this->actingAs($teacher)
        ->delete("/defense-attempts/{$attempt->id}/reviewers/{$teacher->id}")
        ->assertSessionHasErrors('reviewer');

    expect(DefenseAttemptReviewer::where('defense_attempt_id', $attempt->id)
        ->where('reviewer_id', $teacher->id)
        ->where('status', 'active')
        ->count())->toBe(1);
    expect($team->fresh()->members()->whereKey($teacher->id)->exists())->toBeTrue();
});

it('keeps one owner assignment when a re-defense copies reviewers from the previous attempt', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->post("/subjects/{$subject->id}/teams", ['name' => 'Team Re-defense Owner'])
        ->assertRedirect();

    $team = Team::where('subject_id', $subject->id)
        ->where('name', 'Team Re-defense Owner')
        ->firstOrFail();
    $period = $subject->defensePeriods()->where('type', 'midterm')->firstOrFail();

    $this->actingAs($teacher)
        ->post("/defense-periods/{$period->id}/attempts", [
            'team_id' => $team->id,
            'attempt_type' => 're_defense',
        ])
        ->assertRedirect();

    $reDefense = $team->defenseAttempts()
        ->where('defense_period_id', $period->id)
        ->where('attempt_number', 2)
        ->firstOrFail();

    expect(DefenseAttemptReviewer::where('defense_attempt_id', $reDefense->id)
        ->where('reviewer_id', $teacher->id)
        ->count())->toBe(1);
});

it('allows the subject owner to add an examiner role to their required assignment', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->post("/subjects/{$subject->id}/teams", ['name' => 'Team Owner Examiner'])
        ->assertRedirect();

    $team = Team::where('subject_id', $subject->id)
        ->where('name', 'Team Owner Examiner')
        ->firstOrFail();
    $attempt = $team->defenseAttempts()->firstOrFail();

    $this->actingAs($teacher)
        ->patch("/defense-attempts/{$attempt->id}/reviewers/{$teacher->id}/role", [
            'committee_role' => 'technical_examiner',
        ])
        ->assertRedirect();

    expect(DefenseAttemptReviewer::where('defense_attempt_id', $attempt->id)
        ->where('reviewer_id', $teacher->id)
        ->value('committee_role'))->toBe('Technical examiner');

    $this->actingAs($teacher)
        ->patch("/defense-attempts/{$attempt->id}/reviewers/{$teacher->id}/role", [
            'committee_role' => 'fyp_instructor',
        ])
        ->assertRedirect();

    expect(DefenseAttemptReviewer::where('defense_attempt_id', $attempt->id)
        ->where('reviewer_id', $teacher->id)
        ->value('committee_role'))->toBe('fyp_instructor');
});

it('allows teacher to delete a team', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();

    $this->actingAs($teacher)
        ->delete("/teams/{$team->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('teams', ['id' => $team->id]);
});

it('allows adding members to a team', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $student = User::factory()->student()->create();

    $this->actingAs($teacher)
        ->post("/teams/{$team->id}/members", ['email' => $student->email])
        ->assertRedirect();

    expect($team->fresh()->members)->toHaveCount(1);
});

it('does not add approved reviewers as team members', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $reviewer = User::factory()->teacher()->create();

    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);

    $this->actingAs($teacher)
        ->post("/teams/{$team->id}/members", ['email' => $reviewer->email])
        ->assertSessionHasErrors('email');

    expect($team->fresh()->members()->whereKey($reviewer->id)->exists())->toBeFalse();
    expect(DefenseAttemptReviewer::where('reviewer_id', $reviewer->id)->exists())->toBeFalse();
});

it('allows a team student to update the team topic', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $student = User::factory()->student()->create();
    $team = Team::factory()->for($subject)->create();

    $subject->students()->attach($student, ['role' => 'student', 'status' => 'approved']);
    $team->members()->attach($student);

    $this->actingAs($student)
        ->patch("/teams/{$team->id}/topic", [
            'topic' => 'Scormetry 2.0: AI Rubric Generation and Secure Document Management for Academic Evaluations',
        ])
        ->assertRedirect();

    expect($team->fresh()->topic)->toBe('Scormetry 2.0: AI Rubric Generation and Secure Document Management for Academic Evaluations');
});

it('does not allow a reviewer assignment to update the team topic', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $reviewer = User::factory()->teacher()->create();
    $team = Team::factory()->for($subject)->create();

    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $team->members()->attach($reviewer);

    $this->actingAs($reviewer)
        ->patch("/teams/{$team->id}/topic", [
            'topic' => 'Reviewer should not edit this topic',
        ])
        ->assertForbidden();

    expect($team->fresh()->topic)->toBeNull();
});

it('does not show reviewer defense assignments on the my team page', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $reviewer = User::factory()->teacher()->create();

    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $team->members()->attach($reviewer);

    $this->actingAs($reviewer)
        ->get('/teams')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/Index')
            ->has('teams', 0));
});

it('shows the advisor instead of assigned reviewers on the my team page', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $student = User::factory()->student()->create();
    $advisor = User::factory()->teacher()->create();
    $reviewer = User::factory()->teacher()->create();
    $team = Team::factory()->for($subject)->create([
        'advisor_id' => $advisor->id,
        'topic' => 'AI Rubric Generation',
    ]);

    $subject->students()->attach($student, ['role' => 'student', 'status' => 'approved']);
    $subject->reviewers()->attach($reviewer, ['role' => 'guest_panel', 'status' => 'approved']);
    $team->members()->attach([$student->id, $reviewer->id]);

    $this->actingAs($student)
        ->get('/teams')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/Index')
            ->has('teams', 1)
            ->where('teams.0.advisor.id', $advisor->id)
            ->where('teams.0.topic', 'AI Rubric Generation')
            ->has('teams.0.student_members', 1));
});

it('allows removing members from a team', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();
    $student = User::factory()->student()->create();
    $team->members()->attach($student);

    $this->actingAs($teacher)
        ->delete("/teams/{$team->id}/members/{$student->id}")
        ->assertRedirect();

    expect($team->fresh()->members)->toHaveCount(0);
});

it('validates team name is required', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->post("/subjects/{$subject->id}/teams", ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('validates member email exists', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $team = Team::factory()->for($subject)->create();

    $this->actingAs($teacher)
        ->post("/teams/{$team->id}/members", ['email' => 'nonexistent@example.com'])
        ->assertSessionHasErrors('email');
});

it('adds the creating student to their own team', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $student = User::factory()->create(['role' => 'student']);
    $subject->students()->attach($student, ['role' => 'student', 'status' => 'approved']);

    $this->actingAs($student)
        ->post("/subjects/{$subject->id}/teams", ['name' => 'My Team'])
        ->assertRedirect();

    $team = Team::where('subject_id', $subject->id)->where('name', 'My Team')->firstOrFail();
    expect($team->members->pluck('id'))->toContain($student->id);
});

it('sets the advisor directly when the owner invites, without making them a judge', function () {
    Mail::fake();
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $advisor = User::factory()->teacher()->create();
    $team = Team::factory()->for($subject)->create();

    $this->actingAs($teacher)
        ->post("/teams/{$team->id}/advisor", ['email' => $advisor->email])
        ->assertRedirect();

    expect($team->fresh()->advisor_id)->toBe($advisor->id);
    // Advisor must NOT become a judge/reviewer.
    expect(DefenseAttemptReviewer::where('reviewer_id', $advisor->id)->exists())->toBeFalse();
});

it('makes a student-invited outside advisor a pending request (not set yet)', function () {
    Mail::fake();
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $student = User::factory()->create(['role' => 'student']);
    $subject->students()->attach($student, ['role' => 'student', 'status' => 'approved']);
    $advisor = User::factory()->teacher()->create(); // NOT in the subject

    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($student);

    $this->actingAs($student)
        ->post("/teams/{$team->id}/advisor", ['email' => $advisor->email])
        ->assertRedirect();

    // Not set yet — waiting on the owner.
    expect($team->fresh()->advisor_id)->toBeNull();
    $this->assertDatabaseHas('team_requests', [
        'team_id' => $team->id,
        'user_id' => $advisor->id,
        'role' => 'advisor',
        'status' => 'pending',
    ]);
});

it('lets the owner approve a pending advisor request', function () {
    Mail::fake();
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $advisor = User::factory()->teacher()->create();
    $team = Team::factory()->for($subject)->create();
    $req = \App\Models\TeamRequest::create([
        'team_id' => $team->id, 'subject_id' => $subject->id, 'email' => $advisor->email,
        'user_id' => $advisor->id, 'role' => 'advisor', 'invited_by' => $teacher->id, 'status' => 'pending',
    ]);

    $this->actingAs($teacher)
        ->post("/team-requests/{$req->id}/approve")
        ->assertRedirect();

    expect($team->fresh()->advisor_id)->toBe($advisor->id);
    expect($req->fresh()->status)->toBe('approved');
});

it('forbids a student from removing the advisor directly', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $student = User::factory()->create(['role' => 'student']);
    $subject->students()->attach($student, ['role' => 'student', 'status' => 'approved']);
    $advisor = User::factory()->teacher()->create();

    $team = Team::factory()->for($subject)->create(['advisor_id' => $advisor->id]);
    $team->members()->attach($student);

    $this->actingAs($student)
        ->delete("/teams/{$team->id}/advisor")
        ->assertForbidden();
    expect($team->fresh()->advisor_id)->toBe($advisor->id);
});

it('lets the owner remove the advisor', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $advisor = User::factory()->teacher()->create();
    $team = Team::factory()->for($subject)->create(['advisor_id' => $advisor->id]);

    $this->actingAs($teacher)
        ->delete("/teams/{$team->id}/advisor")
        ->assertRedirect();

    expect($team->fresh()->advisor_id)->toBeNull();
});

it('makes a student-invited non-enrolled teammate a pending request and lets the owner approve it', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $student = User::factory()->create(['role' => 'student']);
    $subject->students()->attach($student, ['role' => 'student', 'status' => 'approved']);
    $newcomer = User::factory()->create(['role' => 'student']); // not enrolled

    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($student);

    // Student invites someone not in the class -> pending, not added.
    $this->actingAs($student)
        ->post("/teams/{$team->id}/members", ['email' => $newcomer->email])
        ->assertRedirect();
    expect($team->fresh()->members->pluck('id'))->not->toContain($newcomer->id);

    $req = \App\Models\TeamRequest::where('team_id', $team->id)->where('user_id', $newcomer->id)->firstOrFail();

    // Owner approves -> enrolled + joined the team.
    $this->actingAs($teacher)
        ->post("/team-requests/{$req->id}/approve")
        ->assertRedirect();

    expect($team->fresh()->members->pluck('id'))->toContain($newcomer->id);
    expect($subject->students()->where('users.id', $newcomer->id)->exists())->toBeTrue();
});

it('forbids a non-member student from setting another team advisor', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $outsider = User::factory()->create(['role' => 'student']);
    $subject->students()->attach($outsider, ['role' => 'student', 'status' => 'approved']);
    $advisor = User::factory()->teacher()->create();

    $team = Team::factory()->for($subject)->create();

    $this->actingAs($outsider)
        ->post("/teams/{$team->id}/advisor", ['email' => $advisor->email])
        ->assertForbidden();
});

it('lets a team member request advisor removal (notifies owner, advisor stays)', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $student = User::factory()->create(['role' => 'student']);
    $subject->students()->attach($student, ['role' => 'student', 'status' => 'approved']);
    $advisor = User::factory()->teacher()->create();
    $team = Team::factory()->for($subject)->create(['advisor_id' => $advisor->id]);
    $team->members()->attach($student);

    $this->actingAs($student)
        ->post("/teams/{$team->id}/advisor/request-removal")
        ->assertRedirect();

    // Advisor is NOT removed by the request.
    expect($team->fresh()->advisor_id)->toBe($advisor->id);
    // Owner got a notification.
    expect($teacher->notifications()->count())->toBeGreaterThan(0);
});

it('lets a team member request a teammate removal (notifies owner, member stays)', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $a = User::factory()->create(['role' => 'student']);
    $b = User::factory()->create(['role' => 'student']);
    foreach ([$a, $b] as $s) {
        $subject->students()->attach($s, ['role' => 'student', 'status' => 'approved']);
    }
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach([$a->id, $b->id]);

    $this->actingAs($a)
        ->post("/teams/{$team->id}/members/{$b->id}/request-removal")
        ->assertRedirect();

    expect($team->fresh()->members->pluck('id'))->toContain($b->id);
    expect($teacher->notifications()->count())->toBeGreaterThan(0);
});

it('forbids a non-member from requesting a teammate removal', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $member = User::factory()->create(['role' => 'student']);
    $outsider = User::factory()->create(['role' => 'student']);
    $subject->students()->attach($member, ['role' => 'student', 'status' => 'approved']);
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach($member);

    $this->actingAs($outsider)
        ->post("/teams/{$team->id}/members/{$member->id}/request-removal")
        ->assertForbidden();
});

it('records an advisor removal request that the owner can approve to remove the advisor', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $student = User::factory()->create(['role' => 'student']);
    $subject->students()->attach($student, ['role' => 'student', 'status' => 'approved']);
    $advisor = User::factory()->teacher()->create();
    $team = Team::factory()->for($subject)->create(['advisor_id' => $advisor->id]);
    $team->members()->attach($student);

    // Student requests removal -> a pending record exists, advisor stays.
    $this->actingAs($student)->post("/teams/{$team->id}/advisor/request-removal")->assertRedirect();
    $req = \App\Models\TeamRequest::where('team_id', $team->id)->where('role', 'remove_advisor')->where('status', 'pending')->firstOrFail();
    expect($team->fresh()->advisor_id)->toBe($advisor->id);

    // Owner approves -> advisor removed.
    $this->actingAs($teacher)->post("/team-requests/{$req->id}/approve")->assertRedirect();
    expect($team->fresh()->advisor_id)->toBeNull();
    expect($req->fresh()->status)->toBe('approved');
});

it('records a member removal request that the owner can approve to remove the member', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $a = User::factory()->create(['role' => 'student']);
    $b = User::factory()->create(['role' => 'student']);
    foreach ([$a, $b] as $s) {
        $subject->students()->attach($s, ['role' => 'student', 'status' => 'approved']);
    }
    $team = Team::factory()->for($subject)->create();
    $team->members()->attach([$a->id, $b->id]);

    $this->actingAs($a)->post("/teams/{$team->id}/members/{$b->id}/request-removal")->assertRedirect();
    $req = \App\Models\TeamRequest::where('team_id', $team->id)->where('role', 'remove_member')->where('status', 'pending')->firstOrFail();
    expect($team->fresh()->members->pluck('id'))->toContain($b->id);

    $this->actingAs($teacher)->post("/team-requests/{$req->id}/approve")->assertRedirect();
    expect($team->fresh()->members->pluck('id'))->not->toContain($b->id);
});

it('lets the owner reject a removal request, keeping the advisor', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();
    $advisor = User::factory()->teacher()->create();
    $team = Team::factory()->for($subject)->create(['advisor_id' => $advisor->id]);
    $req = \App\Models\TeamRequest::create([
        'team_id' => $team->id, 'subject_id' => $subject->id, 'email' => $advisor->email,
        'user_id' => $advisor->id, 'role' => 'remove_advisor', 'invited_by' => $teacher->id, 'status' => 'pending',
    ]);

    $this->actingAs($teacher)->delete("/team-requests/{$req->id}/reject")->assertRedirect();
    expect($team->fresh()->advisor_id)->toBe($advisor->id);
    expect($req->fresh()->status)->toBe('rejected');
});

it('lets the owner remove a duplicate owner assignment but keeps at least one', function () {
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->post("/subjects/{$subject->id}/teams", ['name' => 'Team Dup'])
        ->assertRedirect();

    $team = Team::where('subject_id', $subject->id)->where('name', 'Team Dup')->firstOrFail();
    $attempt = $team->defenseAttempts()->firstOrFail();

    // The owner already holds a fyp_instructor assignment. Add a duplicate (advisor) row.
    $primary = DefenseAttemptReviewer::where('defense_attempt_id', $attempt->id)
        ->where('reviewer_id', $teacher->id)->where('committee_role', 'fyp_instructor')->firstOrFail();
    $duplicate = DefenseAttemptReviewer::create([
        'defense_attempt_id' => $attempt->id,
        'reviewer_id' => $teacher->id,
        'committee_role' => 'Advisor',
        'status' => 'active',
    ]);

    // Removing the duplicate (one remains) is allowed.
    $this->actingAs($teacher)
        ->delete("/defense-attempts/{$attempt->id}/reviewers/{$teacher->id}", ['assignment_id' => $duplicate->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    expect(DefenseAttemptReviewer::find($duplicate->id))->toBeNull();
    expect(DefenseAttemptReviewer::find($primary->id))->not->toBeNull();

    // Removing the last remaining owner assignment is still blocked.
    $this->actingAs($teacher)
        ->delete("/defense-attempts/{$attempt->id}/reviewers/{$teacher->id}", ['assignment_id' => $primary->id])
        ->assertSessionHasErrors('reviewer');
    expect(DefenseAttemptReviewer::find($primary->id))->not->toBeNull();
});
