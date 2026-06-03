<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'paper_id',
    'defense_attempt_id',
    'defense_attempt_reviewer_id',
    'reviewer_id',
    'committee_role',
    'scores_json',
    'comment',
    'is_submitted',
    'locked_at',
    'unlocked_at',
    'unlock_reason',
    'unlocked_by',
])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scores_json' => 'array',
            'is_submitted' => 'boolean',
            'locked_at' => 'datetime',
            'unlocked_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /** @return BelongsTo<Paper, $this> */
    public function paper(): BelongsTo
    {
        return $this->belongsTo(Paper::class);
    }

    /** @return BelongsTo<DefenseAttempt, $this> */
    public function defenseAttempt(): BelongsTo
    {
        return $this->belongsTo(DefenseAttempt::class);
    }

    /** @return BelongsTo<DefenseAttemptReviewer, $this> */
    public function reviewerAssignment(): BelongsTo
    {
        return $this->belongsTo(DefenseAttemptReviewer::class, 'defense_attempt_reviewer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function unlockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }

    /** @return HasMany<ReviewUnlockLog, $this> */
    public function unlockLogs(): HasMany
    {
        return $this->hasMany(ReviewUnlockLog::class);
    }
}
