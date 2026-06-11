<?php

namespace App\Models;

use Database\Factories\PaperFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['team_id', 'defense_attempt_id', 'subject_id', 'file_path', 'slides_path', 'final_score', 'final_score_override', 'final_score_override_reason', 'final_score_override_by', 'visibility_status', 'turned_in_at'])]
class Paper extends Model
{
    /** @use HasFactory<PaperFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'final_score' => 'decimal:2',
            'final_score_override' => 'decimal:2',
            'turned_in_at' => 'datetime',
        ];
    }

    /**
     * A document is "turned in" (visible to judges/teacher and reviewable) once the
     * student submits it. Before that it's an attached draft only the team can see.
     */
    public function isTurnedIn(): bool
    {
        return $this->turned_in_at !== null;
    }

    public function effectiveFinalScore(): ?float
    {
        return $this->final_score_override !== null
            ? (float) $this->final_score_override
            : ($this->final_score !== null ? (float) $this->final_score : null);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<DefenseAttempt, $this> */
    public function defenseAttempt(): BelongsTo
    {
        return $this->belongsTo(DefenseAttempt::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<User, $this> */
    public function overrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_score_override_by');
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
