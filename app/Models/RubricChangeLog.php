<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rubric_id',
    'changed_by',
    'reason',
    'structure_before',
    'structure_after',
    'scoring_started',
])]
class RubricChangeLog extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'structure_before' => 'array',
            'structure_after' => 'array',
            'scoring_started' => 'boolean',
        ];
    }

    /** @return BelongsTo<Rubric, $this> */
    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
