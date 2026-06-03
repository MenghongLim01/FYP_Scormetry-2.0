<?php

namespace App\Http\Requests;

use App\Models\Paper;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        // Admins can always submit / edit reviews
        if ($user->isAdmin()) {
            return true;
        }

        /** @var Paper|null $paper */
        $paper = $this->route('paper');
        if (! $paper instanceof Paper) {
            return false;
        }

        $paper->loadMissing(['subject', 'team.members', 'defenseAttempt.activeReviewerAssignments']);

        // The subject's owning teacher may review any paper in their subject
        if ($paper->subject->teacher_id === $user->id) {
            return true;
        }

        // Any user who is an *approved reviewer* on the subject AND has been
        // assigned to this specific team may submit a review — regardless of
        // their system-level role (a student-role user can be added as a reviewer).
        $isReviewer = $paper->subject->reviewers()->where('users.id', $user->id)->exists();
        $isAssignedToAttempt = $paper->defenseAttempt
            && $paper->defenseAttempt->activeReviewerAssignments->contains('reviewer_id', $user->id);
        $isAssignedToTeam = $paper->team && $paper->team->members->contains('id', $user->id);

        return $isReviewer && ($isAssignedToAttempt || (! $paper->defense_attempt_id && $isAssignedToTeam));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scores_json' => [
                'required',
                'array',
                // Validate each score against the criterion's own max_score
                function (string $attribute, array $value, \Closure $fail): void {
                    foreach ($value as $i => $entry) {
                        $score    = (float) ($entry['score']     ?? 0);
                        $maxScore = (float) ($entry['max_score'] ?? 0);
                        $criteria = (string) ($entry['criteria'] ?? "entry {$i}");

                        if ($maxScore > 0 && $score > $maxScore) {
                            $fail("Score for criterion \"{$criteria}\" exceeds its maximum of {$maxScore}.");
                        }
                    }
                },
            ],
            'scores_json.*.criteria'  => ['required', 'string'],
            'scores_json.*.score'     => ['required', 'numeric', 'min:0'],
            'scores_json.*.max_score' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'scores_json.*.weight'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scores_json.*.comment'   => ['nullable', 'string', 'max:2000'],
            'comment'      => ['nullable', 'string', 'max:10000'],
            'submit_final' => ['nullable', 'boolean'],
            // Which scoring responsibility (assigned role) this submission is for.
            'defense_attempt_reviewer_id' => ['nullable', 'integer'],
        ];
    }
}
