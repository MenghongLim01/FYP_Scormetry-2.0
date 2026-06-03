<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'defense_attempt_id',
    'reviewer_id',
    'committee_role',
    'status',
    'excluded_from_calculation',
    'removed_at',
    'removed_by',
])]
class DefenseAttemptReviewer extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'excluded_from_calculation' => 'boolean',
            'removed_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** @return BelongsTo<DefenseAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(DefenseAttempt::class, 'defense_attempt_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'defense_attempt_reviewer_id');
    }
}
