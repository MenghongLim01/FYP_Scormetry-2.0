<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaperRequest;
use App\Mail\PaperPublishedMail;
use App\Mail\PaperSubmittedMail;
use App\Models\DefenseAttempt;
use App\Models\DefensePeriod;
use App\Models\Paper;
use App\Models\Subject;
use App\Models\SubjectMember;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaperController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $paperRelations = [
            'team.members',
            'subject.teacher',
            'subject.reviewers',
            'reviews.reviewer',
            'defenseAttempt.period',
            'defenseAttempt.activeReviewerAssignments.reviewer',
        ];

        $reviewerTeamIds = $user->isTeacher()
            ? $user->teams()->pluck('teams.id')
            : collect();

        $papers = match (true) {
            $user->isStudent() => Paper::whereIn('team_id', $user->teams()->pluck('teams.id'))
                ->with($paperRelations)
                ->latest()
                ->get(),
            $user->isTeacher() => Paper::where(function ($query) use ($user, $reviewerTeamIds) {
                // Subjects the teacher owns: see all papers
                $query->whereHas('subject', fn ($s) => $s->where('teacher_id', $user->id))
                    // Subjects the teacher reviews: only papers from teams they were assigned to
                    ->orWhere(function ($q) use ($user, $reviewerTeamIds) {
                        $q->whereHas('subject.reviewers', fn ($r) => $r->where('users.id', $user->id))
                            ->whereIn('team_id', $reviewerTeamIds);
                    });
            })
                ->with($paperRelations)
                ->latest()
                ->get(),
            default => Paper::with($paperRelations)->latest()->get(),
        };

        $this->decoratePaperTeamMemberRoles($papers);

        return Inertia::render('papers/Index', [
            'papers' => $papers,
            'reviewerTeamIds' => $reviewerTeamIds->values(),
        ]);
    }

    /**
     * @param  Collection<int, Paper>  $papers
     */
    private function decoratePaperTeamMemberRoles(Collection $papers): void
    {
        $papers->each(function (Paper $paper): void {
            $team = $paper->team;
            $subject = $paper->subject;

            if (! $team || ! $subject) {
                return;
            }

            $reviewers = $subject->reviewers ?? collect();
            $reviewerRoleById = $reviewers->mapWithKeys(fn (User $reviewer): array => [
                $reviewer->id => $reviewer->pivot?->role_label
                    ?: $this->committeeRoleLabel($reviewer->pivot?->role),
            ]);

            $panelIds = $reviewerRoleById
                ->keys()
                ->push($subject->teacher_id)
                ->filter()
                ->unique()
                ->values();

            $studentMembers = $team->members
                ->reject(fn (User $member): bool => $panelIds->contains($member->id))
                ->values()
                ->map(fn (User $member): array => $member->only(['id', 'name', 'email']));

            $reviewPanel = $team->members
                ->filter(fn (User $member): bool => $panelIds->contains($member->id))
                ->values()
                ->map(function (User $member) use ($subject, $reviewerRoleById): array {
                    $roleLabel = $member->id === $subject->teacher_id
                        ? 'FYP Instructor / Organizer'
                        : (string) ($reviewerRoleById->get($member->id) ?: 'Review Panel');

                    return [
                        ...$member->only(['id', 'name', 'email']),
                        'role_label' => $roleLabel,
                    ];
                });

            $team->setAttribute('student_members', $studentMembers);
            $team->setAttribute('review_panel', $reviewPanel);
        });
    }

    /**
     * Human label for a stored subject-membership role. Retired values
     * (guest_panel) display as the preferred "Custom role" wording; nothing is
     * ever shown as raw snake_case.
     */
    private function committeeRoleLabel(?string $role): string
    {
        return match ($role) {
            'advisor' => 'Advisor',
            'fyp_instructor' => 'FYP Instructor',
            'technical_examiner' => 'Technical examiner',
            'academic_examiner' => 'Academic examiner',
            'guest_panel', 'custom' => 'Custom role',
            null, '' => 'Review Panel',
            default => str($role)->replace('_', ' ')->title()->toString(),
        };
    }

    public function create(Request $request, Subject $subject): Response
    {
        $user = $request->user();
        $membership = SubjectMember::where('subject_id', $subject->id)
            ->where('user_id', $user->id)
            ->first();
        $isApprovedStudent = $membership?->status === 'approved' && $membership?->role === 'student';
        abort_unless($user->isAdmin() || $isApprovedStudent, 403);

        $team = $subject->teams()
            ->whereHas('members', fn ($query) => $query->where('users.id', $user->id))
            ->with(['members', 'defenseAttempts.period', 'defenseAttempts.papers'])
            ->first();

        return Inertia::render('papers/Create', [
            'subject' => $subject,
            'team' => $team,
            'selectedAttemptId' => $request->integer('defense_attempt_id') ?: null,
        ]);
    }

    public function store(StorePaperRequest $request): RedirectResponse
    {
        $subject = Subject::findOrFail($request->validated('subject_id'));
        $membership = SubjectMember::where('subject_id', $subject->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $membership || $membership->status !== 'approved' || $membership->role !== 'student') {
            abort(403);
        }

        $team = Team::where('subject_id', $subject->id)
            ->whereHas('members', fn ($query) => $query->where('users.id', $request->user()->id))
            ->first();

        if (! $team) {
            return back()->withErrors(['team' => 'You must join a team before submitting a paper.']);
        }

        $attempt = $this->ensureSubmissionAttempt(
            $team,
            $request->integer('defense_attempt_id') ?: null,
        );

        if (! $attempt->isPaperUploadOpen()) {
            return back()->withErrors(['file' => 'The upload deadline has passed. Contact your instructor if you need a special replacement window.']);
        }

        if ($attempt->results_released_at !== null) {
            return back()->withErrors(['file' => 'Results have already been released for this defense. The paper cannot be replaced.']);
        }

        $path = $request->file('file')->store('papers', 'private');

        // Optional presentation slides PDF. Only touched when the student actually
        // uploads one, so replacing just the manuscript keeps any existing slides.
        $slidesPath = $request->hasFile('slides')
            ? $request->file('slides')->store('slides', 'private')
            : null;

        $paper = $team->papers()
            ->where('defense_attempt_id', $attempt->id)
            ->latest()
            ->first();

        // Attaching only stages the file as a DRAFT — judges/teacher can't see it
        // yet. The student must click "Turn in" to actually submit. Re-attaching
        // before turn-in just replaces the staged file.
        if ($paper) {
            Storage::disk('private')->delete($paper->file_path);

            $attributes = [
                'file_path' => $path,
                'visibility_status' => 'draft',
                'turned_in_at' => null,
                'final_score' => null,
                'final_score_override' => null,
                'final_score_override_reason' => null,
                'final_score_override_by' => null,
            ];

            // Replace slides only if a new slides file was uploaded; otherwise
            // preserve the existing one (student is just swapping the manuscript).
            if ($slidesPath !== null) {
                if ($paper->slides_path) {
                    Storage::disk('private')->delete($paper->slides_path);
                }
                $attributes['slides_path'] = $slidesPath;
            }

            $paper->update($attributes);
        } else {
            $paper = Paper::create([
                'team_id' => $team->id,
                'defense_attempt_id' => $attempt->id,
                'subject_id' => $subject->id,
                'file_path' => $path,
                'slides_path' => $slidesPath,
                'visibility_status' => 'draft',
                'turned_in_at' => null,
            ]);
        }

        return to_route('papers.show', $paper)
            ->with('success', 'File attached. Review it below, then click "Turn in" to submit.');
    }

    /**
     * Student turns in the attached document — this is the real submission. Only now
     * do judges/the teacher get notified and can review it.
     */
    public function turnIn(Request $request, Paper $paper): RedirectResponse
    {
        $user = $request->user();
        $paper->load(['team.members', 'subject.teacher', 'defenseAttempt.activeReviewerAssignments.reviewer']);

        abort_unless($paper->team && $paper->team->members->contains('id', $user->id), 403);
        abort_if($paper->file_path === null, 422, 'Attach a document before turning it in.');

        $attempt = $paper->defenseAttempt;
        if ($attempt && ! $attempt->isPaperUploadOpen()) {
            return back()->withErrors(['file' => 'The upload deadline has passed.']);
        }
        if ($attempt && $attempt->results_released_at !== null) {
            return back()->withErrors(['file' => 'Results have already been released for this defense.']);
        }

        if ($paper->isTurnedIn()) {
            return back()->with('success', 'This document is already turned in.');
        }

        $paper->update(['visibility_status' => 'submitted', 'turned_in_at' => now()]);

        $this->notifyDocumentTurnedIn($paper);

        return back()->with('success', 'Document turned in. Your judges can now review it.');
    }

    /**
     * Student unsubmits (reverts a turned-in document back to an editable draft) so
     * they can fix or replace it — allowed until the upload deadline.
     */
    public function unsubmit(Request $request, Paper $paper): RedirectResponse
    {
        $user = $request->user();
        $paper->load(['team.members', 'defenseAttempt']);

        abort_unless($paper->team && $paper->team->members->contains('id', $user->id), 403);

        $attempt = $paper->defenseAttempt;
        if ($attempt && ! $attempt->isPaperUploadOpen()) {
            return back()->withErrors(['file' => 'The upload deadline has passed — you can no longer change this document.']);
        }
        if ($attempt && $attempt->results_released_at !== null) {
            return back()->withErrors(['file' => 'Results have already been released — this document is locked.']);
        }

        $paper->update(['visibility_status' => 'draft', 'turned_in_at' => null]);

        return back()->with('success', 'Document unsubmitted. You can now replace or remove it, then turn it in again.');
    }

    /**
     * Student removes the attached (not-yet-turned-in) document so they can attach
     * the correct file. Turned-in documents must be unsubmitted first.
     */
    public function removeSubmission(Request $request, Paper $paper): RedirectResponse
    {
        $user = $request->user();
        $paper->load(['team.members', 'defenseAttempt']);

        abort_unless($paper->team && $paper->team->members->contains('id', $user->id), 403);
        abort_if($paper->isTurnedIn(), 422, 'Unsubmit the document before removing it.');

        $attempt = $paper->defenseAttempt;
        if ($attempt && $attempt->results_released_at !== null) {
            return back()->withErrors(['file' => 'Results have already been released — this document is locked.']);
        }

        if ($paper->file_path) {
            Storage::disk('private')->delete($paper->file_path);
        }
        if ($paper->slides_path) {
            Storage::disk('private')->delete($paper->slides_path);
        }
        $subjectId = $paper->subject_id;
        $paper->delete();

        return to_route('subjects.show', $subjectId)
            ->with('success', 'Attached document removed. You can attach the correct file now.');
    }

    /**
     * Email + in-app notify the teacher and assigned judges that a document is ready.
     */
    private function notifyDocumentTurnedIn(Paper $paper): void
    {
        $paperUrl = route('papers.show', $paper);
        $team = $paper->team;

        if ($paper->subject->teacher) {
            Mail::to($paper->subject->teacher)->send(new PaperSubmittedMail($paper));
            \App\Support\Notify::send(
                $paper->subject->teacher,
                'New paper submitted',
                $team->name.' turned in a paper for '.$paper->subject->title.'.',
                $paperUrl,
                'paper',
            );
        }

        $reviewers = $paper->defenseAttempt?->activeReviewerAssignments
            ->map(fn ($assignment) => $assignment->reviewer)
            ->filter()
            ->reject(fn ($reviewer) => $reviewer->id === $paper->subject->teacher_id)
            ->unique('id')
            ?? collect();

        foreach ($reviewers as $reviewer) {
            Mail::to($reviewer)->send(new PaperSubmittedMail($paper));
        }

        \App\Support\Notify::many(
            $reviewers,
            'Paper ready to review',
            $team->name.' turned in a paper — it\'s ready for your review.',
            $paperUrl,
            'paper',
        );
    }

    public function show(Request $request, Paper $paper): Response
    {
        $user = $request->user();
        $paper->load(['team.members', 'subject.rubric', 'subject.teacher', 'subject.reviewers', 'defenseAttempt.period.rubric', 'defenseAttempt.activeReviewerAssignments', 'reviews.reviewer']);

        if (! $user->isAdmin() && $paper->subject) {
            $membership = SubjectMember::where('subject_id', $paper->subject_id)
                ->where('user_id', $user->id)
                ->first();

            if ($membership?->status === 'pending') {
                return Inertia::render('subjects/PendingApproval', [
                    'subject' => $paper->subject,
                ]);
            }

            if ($membership?->status === 'blocked') {
                return Inertia::render('subjects/Blocked', [
                    'subject' => $paper->subject,
                ]);
            }
        }

        $isTeamMember = $paper->team && $paper->team->members->contains('id', $user->id);
        $isTeacherOrAdmin = $user->isAdmin() || $paper->subject->teacher_id === $user->id;
        $isReviewer = $paper->subject->reviewers()->where('users.id', $user->id)->exists();
        $isAssignedReviewer = $isReviewer && $this->reviewerCanAccessPaper($paper, $user);
        $isTurnedIn = $paper->isTurnedIn();

        // The team always sees their own document (even an attached draft). The
        // teacher/judges (non-admin) only see it once it's TURNED IN.
        $canAccess = $user->isAdmin()
            || ($isTeamMember && ! $isReviewer)
            || ($paper->subject->teacher_id === $user->id && $isTurnedIn)
            || ($isAssignedReviewer && $isTurnedIn);
        abort_unless($canAccess, 403);

        // Students can only see reviews after the review is completed.
        if ($isTeamMember && ! $isTeacherOrAdmin && ! $isReviewer) {
            if ($paper->visibility_status !== 'published') {
                $paper->setRelation('reviews', collect());
            }
        }

        // Judges should not see other judges' scores or feedback. The FYP
        // instructor/admin and released student team still see the full panel.
        if ($isAssignedReviewer && ! $isTeacherOrAdmin) {
            $paper->setRelation(
                'reviews',
                $paper->reviews->where('reviewer_id', $user->id)->values(),
            );
        }

        $this->decoratePaperTeamMemberRoles(collect([$paper]));

        return Inertia::render('papers/Show', [
            'paper' => $paper,
            'paperPdfUrl' => route('papers.pdf', $paper),
            'slidesPdfUrl' => $paper->slides_path ? route('papers.slides', $paper) : null,
            'rubricPdfUrl' => ($paper->defenseAttempt?->period?->rubric ?? $paper->subject->rubric)
                ? route('rubrics.pdf', $paper->defenseAttempt?->period?->rubric ?? $paper->subject->rubric)
                : null,
        ]);
    }

    public function publish(Paper $paper): RedirectResponse
    {
        $user = request()->user();
        $paper->load('subject');
        abort_unless($user->isAdmin() || $paper->subject->teacher_id === $user->id, 403);

        if ($paper->effectiveFinalScore() === null) {
            return back()->withErrors(['paper' => 'Paper has not been fully reviewed yet.']);
        }

        $paper->update(['visibility_status' => 'published']);
        $paper->defenseAttempt?->update([
            'results_released_at' => now(),
            'status' => 'published',
        ]);

        $paper->load(['team.members', 'subject.reviewers']);
        if ($paper->team) {
            foreach ($paper->team->members as $member) {
                Mail::to($member)->send(new PaperPublishedMail($paper));
            }

            // In-app notification so students see the released result in their bell feed.
            // Mirrors DefenseAttemptController@releaseScores / TeamController release:
            // notify only the student members (exclude judges/reviewers on the team).
            $students = $paper->team->members->filter(
                fn ($member) => ! $paper->subject->reviewers->contains('id', $member->id),
            );

            \App\Support\Notify::many(
                $students,
                'Your results are available',
                'Results for '.$paper->team->name.' ('.$paper->subject->title.') have been released.',
                route('teams.result', $paper->team),
                'result',
            );
        }

        return back()->with('success', 'Review marked as completed. Team members have been notified.');
    }

    public function servePdf(Paper $paper): StreamedResponse
    {
        $user = request()->user();
        $paper->load(['team.members', 'subject', 'defenseAttempt.activeReviewerAssignments']);

        if (! $user->isAdmin() && $paper->subject) {
            $membership = SubjectMember::where('subject_id', $paper->subject_id)
                ->where('user_id', $user->id)
                ->first();

            if ($membership?->status === 'pending') {
                abort(403);
            }

            if ($membership?->status === 'blocked') {
                abort(403);
            }
        }

        $isReviewer = $paper->subject->reviewers()->where('users.id', $user->id)->exists();
        $isTurnedIn = $paper->isTurnedIn();

        // Team sees their own file always; teacher/judges only after turn-in.
        $canAccess = $user->isAdmin()
            || ($paper->team && $paper->team->members->contains('id', $user->id) && ! $isReviewer)
            || ($paper->subject->teacher_id === $user->id && $isTurnedIn)
            || ($isReviewer && $isTurnedIn && $this->reviewerCanAccessPaper($paper, $user));

        abort_unless($canAccess, 403);

        $disk = Storage::disk('private');

        if (! $paper->file_path || ! $disk->exists($paper->file_path)) {
            abort(404, 'The submitted paper file is not available. Please upload the paper again.');
        }

        return $disk->response($paper->file_path, 'paper.pdf', [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="paper.pdf"',
        ]);
    }

    public function serveSlidesPdf(Paper $paper): StreamedResponse
    {
        $user = request()->user();
        $paper->load(['team.members', 'subject', 'defenseAttempt.activeReviewerAssignments']);

        if (! $user->isAdmin() && $paper->subject) {
            $membership = SubjectMember::where('subject_id', $paper->subject_id)
                ->where('user_id', $user->id)
                ->first();

            if ($membership?->status === 'pending' || $membership?->status === 'blocked') {
                abort(403);
            }
        }

        $isReviewer = $paper->subject->reviewers()->where('users.id', $user->id)->exists();
        $isTurnedIn = $paper->isTurnedIn();

        // Same access rules as the manuscript: team sees their own file always;
        // teacher/judges only after turn-in.
        $canAccess = $user->isAdmin()
            || ($paper->team && $paper->team->members->contains('id', $user->id) && ! $isReviewer)
            || ($paper->subject->teacher_id === $user->id && $isTurnedIn)
            || ($isReviewer && $isTurnedIn && $this->reviewerCanAccessPaper($paper, $user));

        abort_unless($canAccess, 403);

        $disk = Storage::disk('private');

        if (! $paper->slides_path || ! $disk->exists($paper->slides_path)) {
            abort(404, 'No slides were uploaded for this document.');
        }

        return $disk->response($paper->slides_path, 'slides.pdf', [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="slides.pdf"',
        ]);
    }

    private function ensureSubmissionAttempt(Team $team, ?int $defenseAttemptId = null): DefenseAttempt
    {
        $team->loadMissing('subject');

        if ($defenseAttemptId !== null) {
            return $team->defenseAttempts()->whereKey($defenseAttemptId)->with('period')->firstOrFail();
        }

        $attempt = $team->activeDefenseAttempt();

        if ($attempt) {
            return $attempt;
        }

        $period = DefensePeriod::firstOrCreate(
            ['subject_id' => $team->subject_id, 'type' => 'final'],
            [
                'name' => 'Final Defense',
                'sequence' => 2,
                'score_scale' => 'points_100',
                'passing_score' => $team->subject->passing_score ?: 50,
                'status' => 'setup',
            ],
        );

        return $team->defenseAttempts()->create([
            'defense_period_id' => $period->id,
            'label' => 'Attempt 1',
            'attempt_number' => 1,
            'attempt_type' => 'regular',
            'status' => 'setup',
        ])->load('period');
    }

    private function reviewerCanAccessPaper(Paper $paper, User $user): bool
    {
        if ($paper->defenseAttempt) {
            return $paper->defenseAttempt->activeReviewerAssignments->contains('reviewer_id', $user->id);
        }

        return $paper->team && $paper->team->members->contains('id', $user->id);
    }
}
