<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'defense_period_id',
    'team_id',
    'label',
    'attempt_number',
    'attempt_type',
    'defense_date',
    'defense_time',
    'defense_duration',
    'defense_room',
    'paper_upload_deadline_at',
    'paper_upload_unlocked_until',
    'score_deadline_at',
    'status',
    'final_score',
    'final_score_override',
    'final_score_override_reason',
    'final_score_override_by',
    'results_released_at',
    'reminder_24h_sent_at',
    'reminder_1h_sent_at',
])]
class DefenseAttempt extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'defense_date' => 'date:Y-m-d',
            'paper_upload_deadline_at' => 'datetime',
            'paper_upload_unlocked_until' => 'datetime',
            'score_deadline_at' => 'datetime',
            'final_score' => 'decimal:2',
            'final_score_override' => 'decimal:2',
            'results_released_at' => 'datetime',
            'reminder_24h_sent_at' => 'datetime',
            'reminder_1h_sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The subject owner is the FYP instructor running this round, so they should
        // always have access to every defense attempt in their subject without having
        // to manually request-and-self-approve a reviewer assignment.
        static::created(function (DefenseAttempt $attempt) {
            $attempt->ensureOwnerIsReviewer();
        });
    }

    /**
     * Make sure the subject owner is recorded as an active reviewer + team member
     * for this attempt. Idempotent — safe to call repeatedly on existing attempts.
     *
     * If a stale `pending` or `rejected` row from before this feature existed is
     * found, it is upgraded to `active` because the owner is now an auto-member
     * and shouldn't be gated by their own approval workflow.
     */
    public function ensureOwnerIsReviewer(): void
    {
        $this->loadMissing('team.subject');
        $ownerId = $this->team?->subject?->teacher_id;
        if (! $ownerId) {
            return;
        }

        $assignment = DefenseAttemptReviewer::firstOrNew(
            ['defense_attempt_id' => $this->id, 'reviewer_id' => $ownerId],
        );

        $assignment->fill([
            'committee_role' => $assignment->committee_role ?: 'fyp_instructor',
            'status' => 'active',
            'excluded_from_calculation' => false,
            'removed_at' => null,
            'removed_by' => null,
        ])->save();

        // Sync the owner into team_members so the existing access-control checks
        // (which use `team->members->contains($user->id)`) light up team-room pages.
        $this->team?->members()->syncWithoutDetaching([$ownerId]);
    }

    public function effectiveFinalScore(): ?float
    {
        return $this->final_score_override !== null
            ? (float) $this->final_score_override
            : ($this->final_score !== null ? (float) $this->final_score : null);
    }

    public function isPaperUploadOpen(): bool
    {
        if ($this->paper_upload_unlocked_until !== null && $this->paper_upload_unlocked_until->isFuture()) {
            return true;
        }

        return $this->paper_upload_deadline_at === null || $this->paper_upload_deadline_at->isFuture();
    }

    /** @return BelongsTo<DefensePeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(DefensePeriod::class, 'defense_period_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function overrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_score_override_by');
    }

    /** @return HasMany<DefenseAttemptReviewer, $this> */
    public function reviewerAssignments(): HasMany
    {
        return $this->hasMany(DefenseAttemptReviewer::class);
    }

    /** @return HasMany<DefenseAttemptReviewer, $this> */
    public function activeReviewerAssignments(): HasMany
    {
        return $this->reviewerAssignments()->where('status', 'active');
    }

    /** @return HasMany<Paper, $this> */
    public function papers(): HasMany
    {
        return $this->hasMany(Paper::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
