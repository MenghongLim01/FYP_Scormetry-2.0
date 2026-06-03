<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'subject_id',
    'name',
    'defense_date',
    'defense_time',
    'defense_duration',
    'defense_room',
    'score_deadline_at',
    'results_released_at',
    'reminder_24h_sent_at',
    'reminder_1h_sent_at',
])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'defense_date' => 'date:Y-m-d',
            'score_deadline_at' => 'datetime',
            'results_released_at' => 'datetime',
            'reminder_24h_sent_at' => 'datetime',
            'reminder_1h_sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')->withTimestamps();
    }

    /** @return HasMany<Paper, $this> */
    public function papers(): HasMany
    {
        return $this->hasMany(Paper::class);
    }

    /** @return HasMany<DefenseAttempt, $this> */
    public function defenseAttempts(): HasMany
    {
        return $this->hasMany(DefenseAttempt::class);
    }

    public function activeDefenseAttempt(?int $defenseAttemptId = null): ?DefenseAttempt
    {
        $query = $this->defenseAttempts()
            ->with(['period.rubric', 'activeReviewerAssignments.reviewer'])
            ->orderByDesc('attempt_number');

        if ($defenseAttemptId !== null) {
            return $query->whereKey($defenseAttemptId)->first();
        }

        return $query->whereNull('results_released_at')->first()
            ?? $this->defenseAttempts()
                ->with(['period.rubric', 'activeReviewerAssignments.reviewer'])
                ->orderByDesc('attempt_number')
                ->first();
    }

    public function latestPaper(): ?Paper
    {
        return $this->papers()->latest()->first();
    }
}
