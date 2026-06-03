<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserApprovedMail;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->whereNot('role', 'admin')
            ->withCount([
                'enrolledSubjects as subjects_count',
                'teachingSubjects as teaching_count',
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'status', 'is_blocked', 'created_at']);

        return Inertia::render('admin/Users', [
            'users' => $users,
        ]);
    }

    public function approve(User $user): RedirectResponse
    {
        $user->update(['status' => 'approved']);

        Mail::to($user)->send(new UserApprovedMail($user));

        return back();
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:student,teacher'],
        ]);

        $user->update(['role' => $validated['role']]);

        return back();
    }

    public function block(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->id === $user->id, 403, 'You cannot block your own account.');
        abort_if($user->isAdmin(), 403, 'Cannot block an admin user.');

        $user->update(['is_blocked' => true]);

        return back()->with('success', 'User blocked.');
    }

    public function unblock(User $user): RedirectResponse
    {
        abort_if($user->isAdmin(), 403, 'Cannot unblock an admin user.');

        $user->update(['is_blocked' => false]);

        return back()->with('success', 'User unblocked.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $authUser = request()->user();
        abort_if($authUser && $authUser->id === $user->id, 403, 'You cannot delete your own account.');
        abort_if($user->isAdmin(), 403, 'Cannot delete an admin user.');

        // Delete all subjects owned by this teacher; DB cascades to papers, teams,
        // enrollments, rubrics, reviews, etc. automatically.
        $user->teachingSubjects()->each(fn (Subject $subject) => $subject->delete());

        $user->reviews()->delete();
        $user->teams()->detach();

        $user->delete();

        return back();
    }
}
