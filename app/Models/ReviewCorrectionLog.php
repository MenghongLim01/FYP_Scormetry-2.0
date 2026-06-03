<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'review_id',
    'paper_id',
    'defense_attempt_id',
    'corrected_by',
    'reason',
    'scores_before',
    'scores_after',
    'comment_before',
    'comment_after',
])]
class ReviewCorrectionLog extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scores_before' => 'array',
            'scores_after' => 'array',
        ];
    }

    /** @return BelongsTo<Review, $this> */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
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

    /** @return BelongsTo<User, $this> */
    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
