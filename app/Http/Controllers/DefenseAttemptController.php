<?php

namespace App\Http\Controllers;

use App\Jobs\RemoveDefenseCalendarEvent;
use App\Jobs\SyncDefenseCalendarEvent;
use App\Mail\DefenseScheduledMail;
use App\Mail\ResultReleasedMail;
use App\Models\DefenseAttempt;
use App\Models\DefenseAttemptReviewer;
use App\Models\DefensePeriod;
use App\Models\GoogleCalendarEvent;
use App\Models\Review;
use App\Models\SubjectMember;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DefenseAttemptController extends Controller
{
    private const ATTEMPT_COMMITTEE_ROLE_LABELS = [
        'technical_examiner' => 'Technical examiner',
        'academic_examiner' => 'Academic examiner',
        'advisor' => 'Advisor',
    ];

    public function store(Request $request, DefensePeriod $defensePeriod): RedirectResponse
    {
        $defensePeriod->load('subject');
        $this->authorizeAttemptManagement($request, $defensePeriod->subject_id);

        $validated = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'attempt_type' => ['nullable', 'string', 'in:regular,re_defense'],
        ]);

        $team = $defensePeriod->subject->teams()->whereKey($validated['team_id'])->firstOrFail();
        $lastAttempt = $team->defenseAttempts()
            ->where('defense_period_id', $defensePeriod->id)
            ->with('activeReviewerAssignments')
            ->orderByDesc('attempt_number')
            ->first();

        $attemptNumber = ($lastAttempt?->attempt_number ?? 0) + 1;
        $attemptType = $validated['attempt_type'] ?? ($attemptNumber === 1 ? 'regular' : 're_defense');

        $attempt = $team->defenseAttempts()->create([
            'defense_period_id' => $defensePeriod->id,
            'label' => $attemptNumber === 1 ? 'Attempt 1' : 'Re-defense '.($attemptNumber - 1),
            'attempt_number' => $attemptNumber,
            'attempt_type' => $attemptType,
            'status' => 'setup',
        ]);

        foreach ($lastAttempt?->activeReviewerAssignments ?? [] as $assignment) {
            $attempt->reviewerAssignments()->updateOrCreate(
                ['reviewer_id' => $assignment->reviewer_id],
                [
                    'committee_role' => $assignment->committee_role,
                    'status' => 'active',
                    'excluded_from_calculation' => false,
                ],
            );
        }

        return back()->with('success', $attempt->label.' created for '.$defensePeriod->name.'.');
    }

    public function update(Request $request, DefenseAttempt $defenseAttempt): RedirectResponse
    {
        $defenseAttempt->load('period.subject.reviewers', 'team.members', 'activeReviewerAssignments.reviewer');
        $this->authorizeAttemptManagement($request, $defenseAttempt->period->subject_id);

        $validated = $request->validate([
            'defense_date' => ['nullable', 'date'],
            'defense_time' => ['nullable', 'date_format:H:i'],
            'defense_duration' => ['nullable', 'integer', 'min:5', 'max:480'],
            'defense_room' => ['nullable', 'string', 'max:255'],
            'paper_upload_deadline_at' => ['nullable', 'date'],
            'score_deadline_at' => ['nullable', 'date'],
        ]);
        $validated = $this->withDefaultDeadlines($validated);

        $previousSchedule = $this->scheduleSnapshot($defenseAttempt);

        $defenseAttempt->update([
            ...$validated,
            'status' => ($validated['defense_date'] ?? null) ? 'scheduled' : 'setup',
        ]);

        $this->syncTeamCompatibilityFields($defenseAttempt);

        $defenseAttempt = $defenseAttempt->fresh()
            ->load('period.subject.reviewers', 'team.members', 'activeReviewerAssignments.reviewer');
        $currentSchedule = $this->scheduleSnapshot($defenseAttempt);
        $scheduleChanged = $this->scheduleChanged($previousSchedule, $currentSchedule);

        if ($scheduleChanged && ($previousSchedule['defense_date'] !== null || $currentSchedule['defense_date'] !== null)) {
            $changeType = $this->scheduleChangeType($previousSchedule, $currentSchedule);
            $calendarSchedule = $changeType === 'cancelled' ? $previousSchedule : null;

            foreach ($this->scheduleRecipients($defenseAttempt) as $recipient) {
                try {
                    Mail::to($recipient->email)->queue(new DefenseScheduledMail($defenseAttempt, $changeType, $calendarSchedule));
                } catch (\Throwable $e) {
                    Log::warning('Defense schedule email failed to send', ['error' => $e->getMessage()]);
                }
            }

            // Mirror the change into connected judges' Google Calendars.
            if ($changeType === 'cancelled') {
                $this->removeCalendarForAttempt($defenseAttempt);
            } else {
                $this->syncCalendarForActiveReviewers($defenseAttempt);
            }

            return back()->with('success', match ($changeType) {
                'cancelled' => 'Defense schedule cancelled and calendar updates sent.',
                'updated' => 'Defense schedule updated and calendar invites sent.',
                default => 'Defense schedule set and calendar invites sent.',
            });
        }

        return back()->with('success', 'Defense attempt updated.');
    }

    public function addReviewer(Request $request, DefenseAttempt $defenseAttempt): RedirectResponse
    {
        $defenseAttempt->load('period.subject', 'team');
        $subject = $defenseAttempt->period->subject;
        $this->authorizeAttemptManagement($request, $subject->id);

        $validated = $request->validate([
            'reviewer_id' => ['required', 'exists:users,id'],
            'committee_role' => ['nullable', 'string', 'in:technical_examiner,academic_examiner,advisor,custom'],
            'role_label' => ['nullable', 'string', 'max:100'],
        ]);

        $membership = SubjectMember::where('subject_id', $subject->id)
            ->where('user_id', $validated['reviewer_id'])
            ->where('role', '!=', 'student')
            ->where('status', 'approved')
            ->first();

        if (! $membership) {
            return back()->withErrors(['reviewer_id' => 'Choose an approved reviewer from this subject.']);
        }

        $committeeRole = $this->resolveAttemptCommitteeRole($validated, $membership);

        // A reviewer may hold several scoring roles in one session, so assignments
        // are keyed by (reviewer + role) — not reviewer alone. Reject only an exact
        // duplicate responsibility; a different role creates a new responsibility.
        $duplicate = $defenseAttempt->reviewerAssignments()
            ->where('reviewer_id', $validated['reviewer_id'])
            ->where('committee_role', $committeeRole)
            ->where('status', 'active')
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'reviewer_id' => 'This reviewer already has this scoring role for this defense session.',
            ]);
        }

        $defenseAttempt->reviewerAssignments()->updateOrCreate(
            ['reviewer_id' => $validated['reviewer_id'], 'committee_role' => $committeeRole],
            [
                'status' => 'active',
                'excluded_from_calculation' => false,
                'removed_at' => null,
                'removed_by' => null,
            ],
        );

        $defenseAttempt->team->members()->syncWithoutDetaching([$validated['reviewer_id']]);

        $reviewer = User::findOrFail($validated['reviewer_id']);
        $calendarInviteSent = $this->sendScheduleInviteToReviewer($defenseAttempt, $reviewer);
        SyncDefenseCalendarEvent::dispatch($defenseAttempt->id, $reviewer->id, $committeeRole);

        return back()->with('success', $calendarInviteSent
            ? 'Reviewer assigned as '.$committeeRole.' for '.$defenseAttempt->label.' and calendar invite sent.'
            : 'Reviewer assigned as '.$committeeRole.' for '.$defenseAttempt->label.'.');
    }

    public function requestReviewer(Request $request, DefenseAttempt $defenseAttempt): RedirectResponse
    {
        $defenseAttempt->load('period.subject');
        $subject = $defenseAttempt->period->subject;
        $user = $request->user();

        $membership = SubjectMember::where('subject_id', $subject->id)
            ->where('user_id', $user->id)
            ->where('role', '!=', 'student')
            ->where('status', 'approved')
            ->first();

        abort_unless($membership !== null, 403, 'Only approved reviewers in this subject can request a team.');

        $existing = $defenseAttempt->reviewerAssignments()
            ->where('reviewer_id', $user->id)
            ->first();

        if ($existing?->status === 'active') {
            return back()->with('success', 'You are already approved for this team.');
        }

        if ($existing?->status === 'pending') {
            return back()->with('success', 'Your request is already waiting for instructor approval.');
        }

        $defenseAttempt->reviewerAssignments()->updateOrCreate(
            ['reviewer_id' => $user->id],
            [
                'committee_role' => $this->defaultAttemptCommitteeRole($membership),
                'status' => 'pending',
                'excluded_from_calculation' => false,
                'removed_at' => null,
                'removed_by' => null,
            ],
        );

        return back()->with('success', 'Review request sent. The FYP instructor will approve it before you can access the team room.');
    }

    public function approveReviewer(Request $request, DefenseAttempt $defenseAttempt, User $user): RedirectResponse
    {
        $defenseAttempt->load('period.subject', 'team');
        $subject = $defenseAttempt->period->subject;
        $this->authorizeAttemptManagement($request, $subject->id);

        $membership = SubjectMember::where('subject_id', $subject->id)
            ->where('user_id', $user->id)
            ->where('role', '!=', 'student')
            ->where('status', 'approved')
            ->first();

        if (! $membership && $subject->teacher_id !== $user->id) {
            return back()->withErrors(['reviewer' => 'This user is not an approved reviewer in the subject.']);
        }

        $assignment = $defenseAttempt->reviewerAssignments()
            ->where('reviewer_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'committee_role' => ['nullable', 'string', 'in:technical_examiner,academic_examiner,advisor,custom'],
            'role_label' => ['nullable', 'string', 'max:100'],
        ]);
        $committeeRole = $this->resolveAttemptCommitteeRole($validated, $membership);

        $assignment->update([
            'committee_role' => $committeeRole,
            'status' => 'active',
            'excluded_from_calculation' => false,
            'removed_at' => null,
            'removed_by' => null,
        ]);

        $defenseAttempt->team->members()->syncWithoutDetaching([$user->id]);

        $calendarInviteSent = $this->sendScheduleInviteToReviewer($defenseAttempt, $user);
        SyncDefenseCalendarEvent::dispatch($defenseAttempt->id, $user->id, $committeeRole);

        return back()->with('success', $calendarInviteSent
            ? $user->name.' can now access this team room. Calendar invite sent.'
            : $user->name.' can now access this team room.');
    }

    public function updateReviewerRole(Request $request, DefenseAttempt $defenseAttempt, User $user): RedirectResponse
    {
        $defenseAttempt->load('period.subject', 'team');
        $subject = $defenseAttempt->period->subject;
        $this->authorizeAttemptManagement($request, $subject->id);

        $membership = SubjectMember::where('subject_id', $subject->id)
            ->where('user_id', $user->id)
            ->where('role', '!=', 'student')
            ->where('status', 'approved')
            ->first();

        if (! $membership && $subject->teacher_id !== $user->id) {
            return back()->withErrors(['reviewer' => 'This user is not an approved reviewer in the subject.']);
        }

        $validated = $request->validate([
            'committee_role' => ['required', 'string', 'in:technical_examiner,academic_examiner,advisor,custom,fyp_instructor'],
            'role_label' => ['nullable', 'string', 'max:100'],
            // A reviewer may hold several responsibilities; target one explicitly.
            'assignment_id' => ['nullable', 'integer'],
        ]);

        if (($validated['committee_role'] ?? null) === 'fyp_instructor' && $subject->teacher_id !== $user->id) {
            return back()->withErrors(['reviewer' => 'Only the subject owner can use the FYP Instructor role.']);
        }

        $assignmentQuery = $defenseAttempt->reviewerAssignments()
            ->where('reviewer_id', $user->id)
            ->whereIn('status', ['active', 'pending']);

        if (! empty($validated['assignment_id'])) {
            $assignmentQuery->whereKey($validated['assignment_id']);
        }

        $assignment = $assignmentQuery->firstOrFail();

        $committeeRole = $this->resolveAttemptCommitteeRole(
            $validated,
            $membership,
            $subject->teacher_id === $user->id,
        );

        // Changing this responsibility's role must not collide with another role
        // the same reviewer already holds in this session.
        $collision = $defenseAttempt->reviewerAssignments()
            ->where('reviewer_id', $user->id)
            ->where('committee_role', $committeeRole)
            ->where('id', '!=', $assignment->id)
            ->where('status', 'active')
            ->exists();

        if ($collision) {
            return back()->withErrors([
                'reviewer' => 'This reviewer already has this scoring role for this defense session.',
            ]);
        }

        $assignment->update(['committee_role' => $committeeRole]);

        Review::where('defense_attempt_reviewer_id', $assignment->id)
            ->where('is_submitted', false)
            ->update(['committee_role' => $committeeRole]);

        return back()->with('success', $user->name.' role updated for this defense round.');
    }

    public function rejectReviewer(Request $request, DefenseAttempt $defenseAttempt, User $user): RedirectResponse
    {
        $defenseAttempt->load('period.subject', 'team');
        $this->authorizeAttemptManagement($request, $defenseAttempt->period->subject_id);

        if ($this->isSubjectOwnerForAttempt($defenseAttempt, $user)) {
            return back()->withErrors([
                'reviewer' => 'The subject owner must attend every defense and cannot be removed from this round.',
            ]);
        }

        $assignment = $defenseAttempt->reviewerAssignments()
            ->where('reviewer_id', $user->id)
            ->firstOrFail();

        $assignment->update([
            'status' => 'rejected',
            'excluded_from_calculation' => true,
            'removed_at' => now(),
            'removed_by' => $request->user()->id,
        ]);

        $hasOtherAssignments = DefenseAttemptReviewer::where('reviewer_id', $user->id)
            ->where('status', 'active')
            ->whereHas('attempt', fn ($query) => $query->where('team_id', $defenseAttempt->team_id))
            ->exists();

        if (! $hasOtherAssignments) {
            $defenseAttempt->team->members()->detach($user->id);
        }

        $this->refreshCalendarAfterRoleRemoval($defenseAttempt, $user->id);

        return back()->with('success', $user->name.' request rejected.');
    }

    public function removeReviewer(Request $request, DefenseAttempt $defenseAttempt, User $user): RedirectResponse
    {
        $defenseAttempt->load('period.subject', 'team');
        $this->authorizeAttemptManagement($request, $defenseAttempt->period->subject_id);

        $validated = $request->validate([
            // Optionally remove a single scoring responsibility; otherwise every
            // responsibility this reviewer holds in this session is unassigned.
            'assignment_id' => ['nullable', 'integer'],
        ]);

        $assignments = $defenseAttempt->reviewerAssignments()
            ->where('reviewer_id', $user->id)
            ->when(! empty($validated['assignment_id']), fn ($query) => $query->whereKey($validated['assignment_id']))
            ->get();

        abort_if($assignments->isEmpty(), 404);

        // The subject owner must attend every defense, so they always keep at least
        // one assignment. They CAN clean up a duplicate role as long as one remains.
        if ($this->isSubjectOwnerForAttempt($defenseAttempt, $user)) {
            $ownerActiveTotal = $defenseAttempt->reviewerAssignments()
                ->where('reviewer_id', $user->id)
                ->where('status', 'active')
                ->count();
            $removingActive = $assignments->where('status', 'active')->count();

            if ($ownerActiveTotal - $removingActive < 1) {
                return back()->withErrors([
                    'reviewer' => 'The subject owner must attend every defense and cannot be removed from this round. You can remove a duplicate role, but at least one must remain.',
                ]);
            }
        }

        // A submitted/locked score is an academic record — the scoring role can no
        // longer be removed. The instructor must unlock the review first if a
        // correction is genuinely needed.
        $lockedRole = $assignments->first(fn ($assignment) => Review::where('defense_attempt_reviewer_id', $assignment->id)
            ->where('is_submitted', true)
            ->exists());

        if ($lockedRole !== null) {
            return back()->withErrors([
                'reviewer' => 'This scoring role already has a submitted score, so it cannot be removed. Unlock the review first if a correction is needed.',
            ]);
        }

        foreach ($assignments as $assignment) {
            $assignment->delete();
        }

        $hasOtherAssignments = DefenseAttemptReviewer::where('reviewer_id', $user->id)
            ->where('status', 'active')
            ->whereHas('attempt', fn ($query) => $query->where('team_id', $defenseAttempt->team_id))
            ->exists();

        if (! $hasOtherAssignments) {
            $defenseAttempt->team->members()->detach($user->id);
        }

        $this->refreshCalendarAfterRoleRemoval($defenseAttempt, $user->id);

        return back()->with('success', 'Scoring role unassigned from this defense session.');
    }

    /**
     * Grant a late-upload extension: reopen the paper upload window for this
     * attempt for N more hours, even if the deadline has already passed.
     */
    public function extendUpload(Request $request, DefenseAttempt $defenseAttempt): RedirectResponse
    {
        $defenseAttempt->load('period.subject', 'team.members');
        $this->authorizeAttemptManagement($request, $defenseAttempt->period->subject_id);

        $validated = $request->validate([
            'hours' => ['required', 'integer', 'in:6,12,24,48,72'],
        ]);

        $until = now()->addHours((int) $validated['hours']);
        $defenseAttempt->update(['paper_upload_unlocked_until' => $until]);

        // Let the student team know their window reopened.
        $subject = $defenseAttempt->period->subject;
        $students = $defenseAttempt->team->members->filter(
            fn ($member) => ! $subject->reviewers()->where('users.id', $member->id)->exists(),
        );
        Notify::many(
            $students,
            'Upload window extended',
            'Your instructor reopened the paper upload for '.$defenseAttempt->label.' until '.$until->format('M j, g:i A').'.',
            route('subjects.show', $subject),
            'deadline',
        );

        return back()->with('success', 'Upload window reopened until '.$until->format('M j, g:i A').'.');
    }

    public function destroy(Request $request, DefenseAttempt $defenseAttempt): RedirectResponse
    {
        $defenseAttempt->load('period.subject', 'papers.reviews');
        $this->authorizeAttemptManagement($request, $defenseAttempt->period->subject_id);

        if ($defenseAttempt->attempt_type !== 're_defense') {
            return back()->withErrors(['attempt' => 'Only re-defense attempts can be removed.']);
        }

        if ($defenseAttempt->results_released_at !== null) {
            return back()->withErrors(['attempt' => 'Results have already been released for this re-defense. It cannot be removed.']);
        }

        $hasSubmittedReview = $defenseAttempt->papers
            ->flatMap(fn ($paper) => $paper->reviews)
            ->contains(fn ($review) => (bool) $review->is_submitted);

        if ($hasSubmittedReview) {
            return back()->withErrors(['attempt' => 'A submitted review exists for this re-defense. It cannot be removed.']);
        }

        $label = $defenseAttempt->label;

        // Remove any synced Google Calendar events before the attempt (and its
        // tracking rows) are deleted.
        $this->removeCalendarForAttempt($defenseAttempt);

        $defenseAttempt->delete();

        return back()->with('success', $label.' removed.');
    }

    public function overrideScore(Request $request, DefenseAttempt $defenseAttempt): RedirectResponse
    {
        $defenseAttempt->load('period');
        $this->authorizeAttemptManagement($request, $defenseAttempt->period->subject_id);

        $maxScore = $defenseAttempt->period->score_scale === 'gpa_4' ? 4 : 100;
        $validated = $request->validate([
            'override_score' => ['required', 'numeric', 'min:0', 'max:'.$maxScore],
            'override_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $defenseAttempt->update([
            'final_score_override' => $validated['override_score'],
            'final_score_override_reason' => $validated['override_note'] ?? null,
            'final_score_override_by' => $request->user()->id,
        ]);

        $paper = $defenseAttempt->papers()->latest()->first();

        if ($paper) {
            $paper->update([
                'final_score_override' => $validated['override_score'],
                'final_score_override_reason' => $validated['override_note'] ?? null,
                'final_score_override_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', 'Score override saved.');
    }

    public function releaseScores(Request $request, DefenseAttempt $defenseAttempt): RedirectResponse
    {
        $defenseAttempt->load('period.subject.reviewers', 'team.members', 'papers');
        $subject = $defenseAttempt->period->subject;
        $this->authorizeAttemptManagement($request, $subject->id);

        $paper = $defenseAttempt->papers()->latest()->first();
        abort_if($paper === null, 422, 'This attempt has no paper to release.');

        // Integrity guard: results can't be released before a score is calculated.
        if ($paper->effectiveFinalScore() === null) {
            return back()->withErrors([
                'release' => 'This team has no calculated score yet. At least one judge must submit a review before results can be released.',
            ]);
        }

        $paper->update(['visibility_status' => 'published']);
        $defenseAttempt->update([
            'results_released_at' => now(),
            'status' => 'published',
        ]);

        $this->syncTeamCompatibilityFields($defenseAttempt);

        $students = $defenseAttempt->team->members->filter(
            fn ($member) => ! $subject->reviewers->contains('id', $member->id),
        );

        foreach ($students as $student) {
            try {
                Mail::to($student)->queue(new ResultReleasedMail($defenseAttempt->team));
            } catch (\Throwable $e) {
                Log::warning('Result released email failed to send', ['error' => $e->getMessage()]);
            }
        }

        Notify::many(
            $students,
            'Your results are available',
            'Results for '.$defenseAttempt->label.' ('.$subject->title.') have been released.',
            route('teams.result', $defenseAttempt->team),
            'result',
        );

        return back()->with('success', 'Results released for '.$defenseAttempt->label.' and students notified.');
    }

    private function authorizeAttemptManagement(Request $request, int $subjectId): void
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || $user->teachingSubjects()->whereKey($subjectId)->exists(),
            403,
        );
    }

    private function syncTeamCompatibilityFields(DefenseAttempt $defenseAttempt): void
    {
        $defenseAttempt->team()->update([
            'defense_date' => $defenseAttempt->defense_date,
            'defense_time' => $defenseAttempt->defense_time,
            'defense_duration' => $defenseAttempt->defense_duration,
            'defense_room' => $defenseAttempt->defense_room,
            'score_deadline_at' => $defenseAttempt->score_deadline_at,
            'results_released_at' => $defenseAttempt->results_released_at,
            'reminder_24h_sent_at' => $defenseAttempt->reminder_24h_sent_at,
            'reminder_1h_sent_at' => $defenseAttempt->reminder_1h_sent_at,
        ]);
    }

    /**
     * Push (create/update) the Google Calendar event for every active reviewer
     * who has connected their calendar. Reviewers without a connection are
     * untouched here — they still receive the .ics email invite as a fallback.
     */
    private function syncCalendarForActiveReviewers(DefenseAttempt $defenseAttempt): void
    {
        $defenseAttempt->loadMissing('activeReviewerAssignments.reviewer');

        foreach ($defenseAttempt->activeReviewerAssignments as $assignment) {
            SyncDefenseCalendarEvent::dispatch(
                $defenseAttempt->id,
                $assignment->reviewer_id,
                $assignment->committee_role,
            );
        }
    }

    /**
     * Remove every synced Google Calendar event for this attempt (used when a
     * defense is cancelled or the attempt is deleted).
     */
    private function removeCalendarForAttempt(DefenseAttempt $defenseAttempt): void
    {
        $events = GoogleCalendarEvent::where('defense_attempt_id', $defenseAttempt->id)
            ->whereNotNull('google_event_id')
            ->get();

        foreach ($events as $event) {
            RemoveDefenseCalendarEvent::dispatch($event->user_id, $event->google_event_id);
        }

        GoogleCalendarEvent::where('defense_attempt_id', $defenseAttempt->id)->delete();
    }

    /**
     * After a role is removed/rejected, re-sync if the reviewer still holds an
     * active responsibility on this attempt, otherwise remove their event.
     */
    private function refreshCalendarAfterRoleRemoval(DefenseAttempt $defenseAttempt, int $reviewerId): void
    {
        $remaining = $defenseAttempt->reviewerAssignments()
            ->where('reviewer_id', $reviewerId)
            ->where('status', 'active')
            ->first();

        if ($remaining) {
            SyncDefenseCalendarEvent::dispatch($defenseAttempt->id, $reviewerId, $remaining->committee_role);

            return;
        }

        $this->removeCalendarForReviewer($defenseAttempt, $reviewerId);
    }

    /**
     * Remove a single reviewer's synced event for this attempt (used when a
     * reviewer is unassigned or their request is rejected).
     */
    private function removeCalendarForReviewer(DefenseAttempt $defenseAttempt, int $reviewerId): void
    {
        $events = GoogleCalendarEvent::where('defense_attempt_id', $defenseAttempt->id)
            ->where('user_id', $reviewerId)
            ->whereNotNull('google_event_id')
            ->get();

        foreach ($events as $event) {
            RemoveDefenseCalendarEvent::dispatch($event->user_id, $event->google_event_id);
        }

        GoogleCalendarEvent::where('defense_attempt_id', $defenseAttempt->id)
            ->where('user_id', $reviewerId)
            ->delete();
    }

    /**
     * @return array{defense_date: string|null, defense_time: string|null, defense_duration: int|null, defense_room: string|null, paper_upload_deadline_at: string|null, score_deadline_at: string|null}
     */
    private function scheduleSnapshot(DefenseAttempt $defenseAttempt): array
    {
        return [
            'defense_date' => $defenseAttempt->defense_date?->format('Y-m-d'),
            'defense_time' => $defenseAttempt->defense_time ? substr($defenseAttempt->defense_time, 0, 5) : null,
            'defense_duration' => $defenseAttempt->defense_duration,
            'defense_room' => $defenseAttempt->defense_room,
            'paper_upload_deadline_at' => $defenseAttempt->paper_upload_deadline_at?->toISOString(),
            'score_deadline_at' => $defenseAttempt->score_deadline_at?->toISOString(),
        ];
    }

    /**
     * @param  array{defense_date?: string|null, defense_time?: string|null, defense_duration?: int|null, defense_room?: string|null, paper_upload_deadline_at?: mixed, score_deadline_at?: mixed}  $validated
     * @return array{defense_date?: string|null, defense_time?: string|null, defense_duration?: int|null, defense_room?: string|null, paper_upload_deadline_at?: mixed, score_deadline_at?: mixed}
     */
    private function withDefaultDeadlines(array $validated): array
    {
        $startsAt = $this->scheduleStartsAt($validated);

        if (! $startsAt) {
            return $validated;
        }

        if (empty($validated['paper_upload_deadline_at'])) {
            $validated['paper_upload_deadline_at'] = $startsAt->copy()->subDay()->setTime(12, 0);
        }

        if (empty($validated['score_deadline_at'])) {
            $validated['score_deadline_at'] = $startsAt->copy()->addDay()->setTime(12, 0);
        }

        return $validated;
    }

    /**
     * @param  array{defense_date?: string|null, defense_time?: string|null}  $schedule
     */
    private function scheduleStartsAt(array $schedule): ?Carbon
    {
        if (empty($schedule['defense_date'])) {
            return null;
        }

        $time = $schedule['defense_time'] ?? '23:59';

        return Carbon::parse($schedule['defense_date'].' '.substr($time, 0, 5), config('app.timezone'));
    }

    /**
     * @param  array<string, mixed>  $previousSchedule
     * @param  array<string, mixed>  $currentSchedule
     */
    private function scheduleChanged(array $previousSchedule, array $currentSchedule): bool
    {
        return $previousSchedule !== $currentSchedule;
    }

    /**
     * @param  array<string, mixed>  $previousSchedule
     * @param  array<string, mixed>  $currentSchedule
     */
    private function scheduleChangeType(array $previousSchedule, array $currentSchedule): string
    {
        if ($previousSchedule['defense_date'] !== null && $currentSchedule['defense_date'] === null) {
            return 'cancelled';
        }

        return $previousSchedule['defense_date'] === null ? 'scheduled' : 'updated';
    }

    private function scheduleRecipients(DefenseAttempt $defenseAttempt): Collection
    {
        $reviewerIds = $defenseAttempt->period->subject->reviewers->pluck('id');
        $studentMembers = $defenseAttempt->team->members->reject(
            fn (User $member) => $reviewerIds->contains($member->id),
        );

        return $studentMembers
            ->merge($defenseAttempt->activeReviewerAssignments->pluck('reviewer'))
            ->filter()
            ->unique('id')
            ->values();
    }

    private function sendScheduleInviteToReviewer(DefenseAttempt $defenseAttempt, User $reviewer): bool
    {
        if ($defenseAttempt->defense_date === null || $defenseAttempt->defense_time === null) {
            return false;
        }

        try {
            Mail::to($reviewer->email)->queue(new DefenseScheduledMail(
                $defenseAttempt->loadMissing('team.subject', 'period'),
                'scheduled',
            ));
        } catch (\Throwable $e) {
            Log::warning('Reviewer schedule invite email failed to send', ['error' => $e->getMessage()]);
        }

        return true;
    }

    /**
     * @param  array{committee_role?: string|null, role_label?: string|null}  $payload
     */
    private function resolveAttemptCommitteeRole(array $payload, ?SubjectMember $membership = null, bool $allowInstructorRole = false): string
    {
        $role = $payload['committee_role'] ?? null;

        if ($role === 'fyp_instructor' && $allowInstructorRole) {
            return 'fyp_instructor';
        }

        if ($role === 'custom') {
            $label = trim((string) ($payload['role_label'] ?? ''));
            abort_if($label === '', 422, 'Role label is required for custom roles.');

            return $label;
        }

        if ($role !== null && isset(self::ATTEMPT_COMMITTEE_ROLE_LABELS[$role])) {
            return self::ATTEMPT_COMMITTEE_ROLE_LABELS[$role];
        }

        return $this->defaultAttemptCommitteeRole($membership);
    }

    private function defaultAttemptCommitteeRole(?SubjectMember $membership = null): string
    {
        if ($membership?->role === 'advisor') {
            return 'Advisor';
        }

        if ($membership?->role === 'fyp_instructor') {
            return 'FYP Instructor';
        }

        $label = trim((string) ($membership?->role_label ?? ''));

        return in_array(strtolower($label), ['', 'guest panel', 'reviewer'], true)
            ? 'Technical examiner'
            : $label;
    }

    private function isSubjectOwnerForAttempt(DefenseAttempt $defenseAttempt, User $user): bool
    {
        return $defenseAttempt->period->subject->teacher_id === $user->id;
    }
}
