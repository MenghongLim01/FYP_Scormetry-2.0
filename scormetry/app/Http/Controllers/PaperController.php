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

        $reviewerTeamIds = $user->isTeacher()
            ? $user->teams()->pluck('teams.id')
            : collect();

        $papers = match (true) {
            $user->isStudent() => Paper::whereIn('team_id', $user->teams()->pluck('teams.id'))
                ->with(['team.members', 'subject', 'reviews.reviewer'])
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
                ->with(['team.members', 'subject', 'reviews.reviewer'])
                ->latest()
                ->get(),
            default => Paper::with(['team.members', 'subject'])->latest()->get(),
        };

        return Inertia::render('papers/Index', [
            'papers' => $papers,
            'reviewerTeamIds' => $reviewerTeamIds->values(),
        ]);
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

        $paper = $team->papers()
            ->where('defense_attempt_id', $attempt->id)
            ->latest()
            ->first();

        if ($paper) {
            Storage::disk('private')->delete($paper->file_path);

            $paper->update([
                'file_path' => $path,
                'visibility_status' => 'submitted',
                'final_score' => null,
                'final_score_override' => null,
                'final_score_override_reason' => null,
                'final_score_override_by' => null,
            ]);
        } else {
            $paper = Paper::create([
                'team_id' => $team->id,
                'defense_attempt_id' => $attempt->id,
                'subject_id' => $subject->id,
                'file_path' => $path,
                'visibility_status' => 'submitted',
            ]);
        }

        $paper->load('subject.teacher', 'team');
        if ($paper->subject->teacher) {
            Mail::to($paper->subject->teacher)->send(new PaperSubmittedMail($paper));
        }

        return to_route('papers.index')
            ->with('success', 'Paper uploaded successfully.');
    }

    public function show(Request $request, Paper $paper): Response
    {
        $user = $request->user();
        $paper->load(['team.members', 'subject.rubric', 'defenseAttempt.period.rubric', 'defenseAttempt.activeReviewerAssignments', 'reviews.reviewer']);

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

        $canAccess = $isTeacherOrAdmin || $isAssignedReviewer
            || ($isTeamMember && ! $isReviewer); // student team member
        abort_unless($canAccess, 403);

        // Students can only see reviews after the review is completed
        if ($isTeamMember && ! $isTeacherOrAdmin && ! $isReviewer) {
            if ($paper->visibility_status !== 'published') {
                $paper->setRelation('reviews', collect());
            }
        }

        return Inertia::render('papers/Show', [
            'paper' => $paper,
            'paperPdfUrl' => route('papers.pdf', $paper),
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

        $paper->load('team.members', 'subject');
        if ($paper->team) {
            foreach ($paper->team->members as $member) {
                Mail::to($member)->send(new PaperPublishedMail($paper));
            }
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

        $canAccess = $user->isAdmin()
            || $paper->subject->teacher_id === $user->id
            || ($isReviewer && $this->reviewerCanAccessPaper($paper, $user))
            || ($paper->team && $paper->team->members->contains('id', $user->id) && ! $isReviewer);

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
