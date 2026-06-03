<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Paper;
use App\Models\Subject;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/Reports', [
            'counts' => [
                'subjects' => Subject::count(),
                'teams' => Team::count(),
                'papers' => Paper::count(),
                'scoredPapers' => Paper::whereNotNull('final_score')->count(),
            ],
        ]);
    }

    public function scoresCsv(Request $request): StreamedResponse
    {
        $papers = Paper::with([
            'team:id,name,subject_id',
            'team.members:id,name,email',
            'subject:id,title,teacher_id,passing_score',
            'subject.teacher:id,name',
            'defenseAttempt.period:id,name,type',
            'defenseAttempt:id,defense_period_id,label,attempt_number,attempt_type,defense_date',
            'overrideBy:id,name',
        ])->orderBy('subject_id')->orderBy('team_id')->get();

        return $this->streamCsv('scores.csv', [
            'Subject', 'Subject Teacher', 'Team', 'Members', 'Round', 'Attempt',
            'Defense Date', 'Final Score', 'Override Score', 'Override Reason',
            'Override By', 'Passing Score', 'Pass/Fail', 'Visibility',
        ], function () use ($papers) {
            foreach ($papers as $paper) {
                $effective = $paper->effectiveFinalScore();
                $passing = $paper->subject?->passing_score;
                yield [
                    $paper->subject?->title ?? '—',
                    $paper->subject?->teacher?->name ?? '—',
                    $paper->team?->name ?? '—',
                    $paper->team?->members->pluck('name')->implode('; ') ?? '',
                    $paper->defenseAttempt?->period?->name ?? '—',
                    $paper->defenseAttempt?->label ?? '—',
                    $paper->defenseAttempt?->defense_date?->toDateString() ?? '—',
                    $paper->final_score !== null ? (float) $paper->final_score : '',
                    $paper->final_score_override !== null ? (float) $paper->final_score_override : '',
                    $paper->final_score_override_reason ?? '',
                    $paper->overrideBy?->name ?? '',
                    $passing ?? '',
                    $effective !== null && $passing !== null ? ($effective >= $passing ? 'Pass' : 'Fail') : '',
                    $paper->visibility_status,
                ];
            }
        });
    }

    public function subjectsCsv(Request $request): StreamedResponse
    {
        $subjects = Subject::with(['teacher:id,name'])
            ->withCount(['students', 'reviewers', 'teams', 'papers'])
            ->orderBy('title')
            ->get();

        return $this->streamCsv('subjects.csv', [
            'Title', 'Teacher', 'Passing Score', 'Require Approval',
            'Students', 'Reviewers', 'Teams', 'Papers', 'Join Code', 'Reviewer Code', 'Created',
        ], function () use ($subjects) {
            foreach ($subjects as $subject) {
                yield [
                    $subject->title,
                    $subject->teacher?->name ?? '—',
                    $subject->passing_score,
                    $subject->require_approval ? 'Yes' : 'No',
                    $subject->students_count,
                    $subject->reviewers_count,
                    $subject->teams_count,
                    $subject->papers_count,
                    $subject->join_code ?? '',
                    $subject->reviewer_code ?? '',
                    $subject->created_at?->toDateString() ?? '',
                ];
            }
        });
    }

    public function teamsCsv(Request $request): StreamedResponse
    {
        $teams = Team::with([
            'subject:id,title,teacher_id',
            'subject.teacher:id,name',
            'members:id,name,email',
        ])->withCount('papers')->orderBy('subject_id')->orderBy('name')->get();

        return $this->streamCsv('teams.csv', [
            'Subject', 'Subject Teacher', 'Team', 'Members', 'Defense Date',
            'Defense Time', 'Room', 'Papers Submitted', 'Results Released',
        ], function () use ($teams) {
            foreach ($teams as $team) {
                yield [
                    $team->subject?->title ?? '—',
                    $team->subject?->teacher?->name ?? '—',
                    $team->name,
                    $team->members->pluck('name')->implode('; '),
                    $team->defense_date?->toDateString() ?? '',
                    $team->defense_time ?? '',
                    $team->defense_room ?? '',
                    $team->papers_count,
                    $team->results_released_at?->toIso8601String() ?? '',
                ];
            }
        });
    }

    /**
     * Stream a CSV with a header row + a generator yielding rows. Wraps fputcsv
     * with a UTF-8 BOM so Excel opens it without garbled accents.
     *
     * @param  array<int, string>  $header
     * @param  callable(): \Generator<int, array<int, mixed>>  $rowGenerator
     */
    private function streamCsv(string $filename, array $header, callable $rowGenerator): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rowGenerator) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rowGenerator() as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
