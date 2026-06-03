<?php

namespace App\Models;

use Database\Factories\PaperFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['team_id', 'defense_attempt_id', 'subject_id', 'file_path', 'final_score', 'final_score_override', 'final_score_override_reason', 'final_score_override_by', 'visibility_status'])]
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
        ];
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

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
