<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRubricRequest;
use App\Http\Requests\UpdateRubricRequest;
use App\Models\DefensePeriod;
use App\Models\Review;
use App\Models\Rubric;
use App\Models\RubricChangeLog;
use App\Models\Subject;
use App\Services\RubricStructureExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RubricController extends Controller
{
    public function create(Subject $subject): Response
    {
        $user = request()->user();
        abort_unless($user->isAdmin() || $subject->teacher_id === $user->id, 403);

        $subject->load('defensePeriods.rubric');

        return Inertia::render('rubrics/Create', [
            'subject' => $subject,
            'defensePeriods' => $subject->defensePeriods,
        ]);
    }

    public function store(
        StoreRubricRequest $request,
        Subject $subject,
        RubricStructureExtractor $rubricStructureExtractor,
    ): RedirectResponse {
        $pdf = $request->file('pdf');
        $structure = $rubricStructureExtractor->extractFromPdf($pdf);
        $path = $pdf->store('rubrics', 'private');

        $validated = $request->validated();

        if (($validated['defense_period_id'] ?? null) !== null) {
            abort_unless(
                $subject->defensePeriods()->whereKey($validated['defense_period_id'])->exists(),
                422,
                'Choose a defense period from this subject.',
            );
        }

        $defensePeriodId = $request->boolean('use_custom_period')
            ? $this->createCustomDefensePeriod($subject, $validated['custom_period_name'])->id
            : ($validated['defense_period_id']
            ?? $subject->defensePeriods()->where('type', 'final')->value('id')
            ?? $subject->defensePeriods()->orderBy('sequence')->value('id'));

        $existingRubric = $defensePeriodId
            ? $subject->rubrics()->where('defense_period_id', $defensePeriodId)->first()
            : null;

        if ($existingRubric) {
            if ($existingRubric->isLocked()) {
                if (! $request->boolean('replace_locked')) {
                    return back()->withErrors(['pdf' => 'This period rubric is locked. Confirm Upload New Rubric before replacing it.']);
                }

                $this->replaceLockedRubric($request, $existingRubric, $path, $structure);

                return to_route('rubrics.show', $existingRubric)
                    ->with('success', 'New rubric PDF uploaded. Review the extracted criteria before locking it again.');
            }

            Storage::disk('private')->delete($existingRubric->pdf_path);
            $existingRubric->update([
                'pdf_path' => $path,
                'structure_json' => $structure !== [] ? $structure : null,
                'status' => $structure !== [] ? 'pending_verification' : 'uploaded',
            ]);
        } else {
            $subject->rubrics()->create([
                'defense_period_id' => $defensePeriodId,
                'pdf_path' => $path,
                'structure_json' => $structure !== [] ? $structure : null,
                'status' => $structure !== [] ? 'pending_verification' : 'uploaded',
            ]);
        }

        return to_route('subjects.show', $subject)
            ->with('success', 'Rubric uploaded successfully.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $structure
     */
    private function replaceLockedRubric(StoreRubricRequest $request, Rubric $rubric, string $path, array $structure): void
    {
        $structureBefore = $rubric->structure_json;
        $scoringStarted = $this->scoringStarted($rubric);

        Storage::disk('private')->delete($rubric->pdf_path);

        $rubric->update([
            'pdf_path' => $path,
            'structure_json' => $structure !== [] ? $structure : null,
            'status' => $structure !== [] ? 'pending_verification' : 'uploaded',
        ]);

        RubricChangeLog::create([
            'rubric_id' => $rubric->id,
            'changed_by' => $request->user()->id,
            'reason' => 'Locked rubric replaced by uploading a new PDF.',
            'structure_before' => $structureBefore,
            'structure_after' => $structure,
            'scoring_started' => $scoringStarted,
        ]);
    }

    public function show(Rubric $rubric): Response
    {
        $user = request()->user();
        $rubric->load('subject.teacher', 'defensePeriod', 'changeLogs.changedBy');

        $canAccess = $user->isAdmin()
            || $rubric->subject->teacher_id === $user->id
            || $rubric->subject->reviewers()->where('users.id', $user->id)->exists()
            || $rubric->subject->students()->where('users.id', $user->id)->exists();

        abort_unless($canAccess, 403);

        return Inertia::render('rubrics/Show', [
            'rubric' => $rubric,
        ]);
    }

    public function servePdf(Rubric $rubric): StreamedResponse
    {
        $user = request()->user();
        $rubric->load('subject');

        $canAccess = $user->isAdmin()
            || $rubric->subject->teacher_id === $user->id
            || $rubric->subject->reviewers()->where('users.id', $user->id)->exists()
            || $rubric->subject->students()->where('users.id', $user->id)->exists();

        abort_unless($canAccess, 403);

        $disk = Storage::disk('private');

        if (! $rubric->pdf_path || ! $disk->exists($rubric->pdf_path)) {
            abort(404, 'The rubric PDF file is not available. Please upload the rubric again.');
        }

        return $disk->response($rubric->pdf_path, 'rubric.pdf', [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="rubric.pdf"',
        ]);
    }

    public function edit(Rubric $rubric): Response|RedirectResponse
    {
        $user = request()->user();
        $rubric->load('subject', 'defensePeriod');
        abort_unless($user->isAdmin() || $rubric->subject->teacher_id === $user->id, 403);

        // Locked rubrics are read-only — there's nothing to edit.
        if ($rubric->isLocked()) {
            return to_route('rubrics.show', $rubric)
                ->with('error', 'This rubric is locked and can no longer be edited.');
        }

        return Inertia::render('rubrics/Edit', [
            'rubric' => $rubric,
            'scoringStarted' => $this->scoringStarted($rubric),
        ]);
    }

    public function update(UpdateRubricRequest $request, Rubric $rubric): RedirectResponse
    {
        // A locked rubric is immutable. Locking is the integrity guarantee that
        // every score is measured against the exact criteria judges saw — editing
        // it afterward would silently re-grade submitted reviews. To fix a locked
        // rubric, create a re-defense attempt with a corrected rubric instead.
        if ($rubric->isLocked()) {
            return back()->withErrors([
                'rubric' => 'This rubric is locked and can no longer be edited. To change the criteria, create a re-defense round with a corrected rubric.',
            ]);
        }

        $structureBefore = $rubric->structure_json;

        $rubric->update([
            'structure_json' => $request->validated('structure_json'),
            'status' => 'pending_verification',
        ]);

        RubricChangeLog::create([
            'rubric_id' => $rubric->id,
            'changed_by' => $request->user()->id,
            'reason' => $request->validated('correction_reason'),
            'structure_before' => $structureBefore,
            'structure_after' => $request->validated('structure_json'),
            'scoring_started' => false,
        ]);

        return to_route('rubrics.show', $rubric)
            ->with('success', 'Rubric structure updated successfully.');
    }

    public function approve(Rubric $rubric): RedirectResponse
    {
        $user = request()->user();
        $rubric->load('subject');
        abort_unless($user->isAdmin() || $rubric->subject->teacher_id === $user->id, 403);

        if ($rubric->isLocked()) {
            return back()->withErrors(['rubric' => 'This rubric is already locked.']);
        }

        $rubric->update(['status' => 'locked']);

        return to_route('subjects.show', $rubric->subject_id)
            ->with('success', 'Rubric approved and locked. Reviews can now begin.');
    }

    public function destroy(Rubric $rubric): RedirectResponse
    {
        $user = request()->user();

        if (! $user->isAdmin() && $user->id !== $rubric->subject->teacher_id) {
            abort(403);
        }

        if ($rubric->isLocked()) {
            return back()->withErrors(['rubric' => 'This rubric is locked and cannot be removed.']);
        }

        $subject = $rubric->subject;

        Storage::disk('private')->delete($rubric->pdf_path);

        $rubric->delete();

        return to_route('rubrics.create', $subject)
            ->with('success', 'Rubric removed. You can now upload a new one.');
    }

    private function scoringStarted(Rubric $rubric): bool
    {
        $rubric->loadMissing('defensePeriod', 'subject');

        if ($rubric->defense_period_id) {
            return Review::whereHas('defenseAttempt', function ($query) use ($rubric) {
                $query->where('defense_period_id', $rubric->defense_period_id);
            })
                ->where('is_submitted', true)
                ->exists();
        }

        return Review::whereHas('paper', function ($query) use ($rubric) {
            $query->where('subject_id', $rubric->subject_id);
        })
            ->where('is_submitted', true)
            ->exists();
    }

    private function createCustomDefensePeriod(Subject $subject, string $name): DefensePeriod
    {
        $name = trim($name);
        $nextSequence = ((int) $subject->defensePeriods()->max('sequence')) + 1;

        $period = $subject->defensePeriods()->firstOrCreate(
            ['name' => $name],
            [
                'type' => 'custom',
                'sequence' => $nextSequence,
                'score_scale' => 'points_100',
                'passing_score' => $subject->passing_score ?: 50,
                'status' => 'setup',
            ],
        );

        $subject->teams()->each(function ($team) use ($period) {
            $team->defenseAttempts()->firstOrCreate(
                [
                    'defense_period_id' => $period->id,
                    'attempt_number' => 1,
                ],
                [
                    'label' => 'Attempt 1',
                    'attempt_type' => 'regular',
                    'status' => 'setup',
                ],
            );
        });

        return $period;
    }
}
