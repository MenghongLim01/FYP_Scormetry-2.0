<?php

namespace App\Http\Controllers;

use App\Models\SubjectInvitation;
use App\Models\SubjectMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = SubjectInvitation::where('token', $token)
            ->whereNull('accepted_at')
            ->firstOrFail();

        if ($request->user()) {
            if ($request->user()->email === $invitation->email) {
                return $this->applyInvitation($invitation, $request);
            }

            return redirect()->route('dashboard')
                ->with('error', 'This invitation was sent to a different email address. Please log in with the correct account.');
        }

        session([
            'invitation_token' => $token,
            'invitation_email' => $invitation->email,
            'invitation_subject' => $invitation->subject->title,
            'invitation_role' => $invitation->committee_role,
        ]);

        $existingUser = User::where('email', $invitation->email)->first();

        if ($existingUser) {
            return redirect()->route('login')
                ->with('status', 'Please log in to accept your invitation to review "'.$invitation->subject->title.'".');
        }

        return redirect()->route('register');
    }

    private function applyInvitation(SubjectInvitation $invitation, Request $request): RedirectResponse
    {
        // Mirror CreateNewUser::applyPendingInvitations — the membership must be
        // explicitly approved (and carry the role label) or the subject's
        // reviewers() relation, which filters on status = 'approved', will never
        // surface this reviewer and they cannot be assigned to teams.
        SubjectMember::updateOrCreate(
            ['subject_id' => $invitation->subject_id, 'user_id' => $request->user()->id],
            [
                'role' => $invitation->committee_role,
                'status' => 'approved',
                'role_label' => $invitation->role_label,
            ],
        );

        $invitation->update(['accepted_at' => now()]);

        return redirect()->route('subjects.show', $invitation->subject_id)
            ->with('success', 'You have been added as a reviewer for '.$invitation->subject->title.'.');
    }
}
