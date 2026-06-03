<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function index(): Response
    {
        $subjects = Subject::query()
            ->with('teacher:id,name')
            ->withCount('memberships')
            ->latest()
            ->get(['id', 'title', 'teacher_id', 'join_code', 'created_at']);

        return Inertia::render('admin/Classrooms', [
            'subjects' => $subjects,
        ]);
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $subject->load(['rubric', 'papers']);

        if ($subject->rubric) {
            Storage::disk('private')->delete($subject->rubric->pdf_path);
        }

        foreach ($subject->papers as $paper) {
            Storage::disk('private')->delete($paper->file_path);
        }

        $subject->delete();

        return back()->with('success', 'Classroom deleted successfully.');
    }

    public function resetJoinCode(Subject $subject): RedirectResponse
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (Subject::where('join_code', $code)->exists());

        $subject->update(['join_code' => $code]);

        return back()->with('success', 'Join code has been reset.');
    }
}
