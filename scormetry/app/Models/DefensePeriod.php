<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'subject_id',
    'name',
    'type',
    'sequence',
    'starts_at',
    'ends_at',
    'score_scale',
    'passing_score',
    'status',
])]
class DefensePeriod extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'date:Y-m-d',
            'ends_at' => 'date:Y-m-d',
            'passing_score' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return HasOne<Rubric, $this> */
    public function rubric(): HasOne
    {
        return $this->hasOne(Rubric::class);
    }

    /** @return HasMany<DefenseAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(DefenseAttempt::class)
            ->orderBy('team_id')
            ->orderBy('attempt_number');
    }
}
