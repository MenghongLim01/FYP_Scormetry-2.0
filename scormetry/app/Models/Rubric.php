<?php

namespace App\Models;

use Database\Factories\RubricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['subject_id', 'defense_period_id', 'pdf_path', 'structure_json', 'status'])]
class Rubric extends Model
{
    /** @use HasFactory<RubricFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'structure_json' => 'array',
        ];
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending_verification';
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<DefensePeriod, $this> */
    public function defensePeriod(): BelongsTo
    {
        return $this->belongsTo(DefensePeriod::class);
    }

    /** @return HasMany<RubricChangeLog, $this> */
    public function changeLogs(): HasMany
    {
        return $this->hasMany(RubricChangeLog::class);
    }
}
