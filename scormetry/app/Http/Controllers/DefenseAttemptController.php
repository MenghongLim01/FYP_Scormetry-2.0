<?php

namespace App\Http\Controllers;

use App\Mail\DefenseScheduledMail;
use App\Mail\ResultReleasedMail;
use App\Models\DefenseAttempt;
use App\Models\DefenseAttemptReviewer;
use App\Models\DefensePeriod;
use App\Models\Review;
use App\Models\SubjectMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class DefenseAttemptController extends Controller
{
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
            $attempt->reviewerAssignments()->create([
                'reviewer_id' => $assignment->reviewer_id,
                'committee_role' => $assignment->committee_role,
                'status' => 'active',
                'excluded_from_calculation' => false,
            ]);
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
                Mail::to($recipient->email)->queue(new DefenseScheduledMail($defenseAttempt, $changeType, $calendarSchedule));
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
        ]);

        $membership = SubjectMember::where('subject_id', $subject->id)
            ->where('user_id', $validated['reviewer_id'])
            ->where('role', '!=', 'student')
            ->where('status', 'approved')
            ->first();

        if (! $membership) {
            return back()->withErrors(['reviewer_id' => 'Choose an approved reviewer from this subject.']);
        }

        $defenseAttempt->reviewerAssignments()->updateOrCreate(
            ['reviewer_id' => $validated['reviewer_id']],
            [
                'committee_role' => $membership->role_label ?: $membership->role,
                'status' => 'active',
                'excluded_from_calculation' => false,
                'removed_at' => null,
                'removed_by' => null,
            ],
        );

        $defenseAttempt->team->members()->syncWithoutDetaching([$validated['reviewer_id']]);

        $reviewer = User::findOrFail($validated['reviewer_id']);
        $calendarInviteSent = $this->sendScheduleInviteToReviewer($defenseAttempt, $reviewer);

        return back()->with('success', $calendarInviteSent
            ? 'Reviewer assigned to '.$defenseAttempt->label.' and calendar invite sent.'
            : 'Reviewer assigned to '.$defenseAttempt->label.'.');
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
                'committee_role' => $membership->role_label ?: $membership->role,
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

        if (! $membership) {
            return back()->withErrors(['reviewer' => 'This user is not an approved reviewer in the subject.']);
        }

        $assignment = $defenseAttempt->reviewerAssignments()
            ->where('reviewer_id', $user->id)
            ->firstOrFail();

        $assignment->update([
            'committee_role' => $membership->role_label ?: $membership->role,
            'status' => 'active',
            'excluded_from_calculation' => false,
            'removed_at' => null,
            'removed_by' => null,
        ]);

        $defenseAttempt->team->members()->syncWithoutDetaching([$user->id]);

        $calendarInviteSent = $this->sendScheduleInviteToReviewer($defenseAttempt, $user);

        return back()->with('success', $calendarInviteSent
            ? $user->name.' can now access this team room. Calendar invite sent.'
            : $user->name.' can now access this team room.');
    }

    public function rejectReviewer(Request $request, DefenseAttempt $defenseAttempt, User $user): RedirectResponse
    {
        $defenseAttempt->load('period.subject', 'team');
        $this->authorizeAttemptManagement($request, $defenseAttempt->period->subject_id);

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

        return back()->with('success', $user->name.' request rejected.');
    }

    public function removeReviewer(Request $request, DefenseAttempt $defenseAttempt, User $user): RedirectResponse
    {
        $defenseAttempt->load('period.subject', 'team');
        $this->authorizeAttemptManagement($request, $defenseAttempt->period->subject_id);

        $assignment = $defenseAttempt->reviewerAssignments()
            ->where('reviewer_id', $user->id)
            ->firstOrFail();

        $hasSubmittedReview = Review::where('defense_attempt_id', $defenseAttempt->id)
            ->where('reviewer_id', $user->id)
            ->where('is_submitted', true)
            ->exists();

        if ($hasSubmittedReview) {
            $assignment->update([
                'status' => 'removed',
                'excluded_from_calculation' => true,
                'removed_at' => now(),
                'removed_by' => $request->user()->id,
            ]);
        } else {
            $assignment->delete();
        }

        $hasOtherAssignments = DefenseAttemptReviewer::where('reviewer_id', $user->id)
            ->where('status', 'active')
            ->whereHas('attempt', fn ($query) => $query->where('team_id', $defenseAttempt->team_id))
            ->exists();

        if (! $hasOtherAssignments) {
            $defenseAttempt->team->members()->detach($user->id);
        }

        return back()->with('success', $hasSubmittedReview
            ? 'Reviewer removed from future calculation. Their submitted score remains in the history.'
            : 'Reviewer unassigned from this attempt.');
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
            Mail::to($student)->send(new ResultReleasedMail($defenseAttempt->team));
        }

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
     * @return array{defense_date: string|null, defense_time: string|null, defense_duration: int|null, defense_room: string|null, score_deadline_at: string|null}
     */
    private function scheduleSnapshot(DefenseAttempt $defenseAttempt): array
    {
        return [
            'defense_date' => $defenseAttempt->defense_date?->format('Y-m-d'),
            'defense_time' => $defenseAttempt->defense_time ? substr($defenseAttempt->defense_time, 0, 5) : null,
            'defense_duration' => $defenseAttempt->defense_duration,
            'defense_room' => $defenseAttempt->defense_room,
            'score_deadline_at' => $defenseAttempt->score_deadline_at?->toISOString(),
        ];
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

    private function scheduleRecipients(DefenseAttempt $defenseAttempt): \Illuminate\Support\Collection
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

        Mail::to($reviewer->email)->queue(new DefenseScheduledMail(
            $defenseAttempt->loadMissing('team.subject', 'period'),
            'scheduled',
        ));

        return true;
    }
}
